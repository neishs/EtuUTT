<?php

namespace Etu\Core\ApiBundle\Controller;

use Doctrine\ORM\EntityManager;
use Etu\Core\ApiBundle\Entity\OauthAuthorization;
use Etu\Core\ApiBundle\Entity\OauthAuthorizationCode;
use Etu\Core\ApiBundle\Entity\OauthClient;
use Etu\Core\ApiBundle\Entity\OauthScope;
use Etu\Core\ApiBundle\Framework\Controller\ApiController;
use Etu\Core\ApiBundle\Oauth\OauthServer;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/oauth")
 */
class SecurityController extends ApiController
{
    /**
     * To access private user data, you need his/her authorization. This endpoint is an HTML page that ask the current
     * logged user if (s)he wants to accept your application. If (s)he doesn't, (s)he is redirected to EtuUTT. I f(s)he
     * does, (s)he is redirected to your application URL with the parameter `code`.
     *
     * Let's take a small example:
     *
     * Your application "MyApp" (reidrect URL: http://myapp.com) wants to access private data of the current user.
     * To do so, you need to redirect user to this page:
     *
     * `/api/oauth/authorize?response_type=code&client_id=<your_client_id>&scope=public%20private_user_account&state=xyz`
     *
     * If the user accept the application, he will be redirected to `http://myapp.com/?code=<authorization_code>`.
     *
     * And using this code (in the parameter `code`), you are now able to get an `access_token` using `/api/oauth/token`.
     *
     * For trusted application, access will be granted without user authentication and without asking. This trusted mode
     * has been created for a specific situation, where user is authenticated whith its student card. In this
     * case, user is authenticated by the client application, and not EtuUTT. EtuUTT have to trust the client application.
     * Please use only this mode if it's really necessary. The application has to give a stuend_id or a login parameter
     * to automatically authenticate user. Note that it's not possible to restrict scope of this application, because
     * user will automatically accept if more scope are requested by application.
     *
     * If an error occured on the page and the client_id is provided, the user will be redirect to:
     * `http://myapp.com/?error=<error_type>&error_description=<error_description>` so you can handle the problem.
     *
     * @ApiDoc(
     *   section = "OAuth",
     *   description = "Display the authorization page for user to allow a given application",
     *   parameters = {
     *      {
     *          "name" = "client_id",
     *          "required" = true,
     *          "dataType" = "string",
     *          "description" = "Your client ID (given in your developper panel)"
     *      },
     *      {
     *          "name" = "scope",
     *          "required" = false,
     *          "dataType" = "string",
     *          "description" = "List of the scopes you need for the token, separated by spaces, for instance: `public private_user_account`. If not provided, grant only access to public scope."
     *      },
     *      {
     *          "name" = "response_type",
     *          "required" = true,
     *          "dataType" = "string",
     *          "description" = "Must be `code` (only authorization_code is supported for the moment)"
     *      },
     *      {
     *          "name" = "state",
     *          "required" = true,
     *          "dataType" = "string",
     *          "description" = "Must be `xyz` (only authorization_code is supported for the moment)"
     *      }
     *   }
     * )
     *
     * @Route("/authorize", name="oauth_authorize")
     * @Method({"GET", "POST"})
     * @Template()
     */
    public function authorizeAction(Request $request)
    {
        /*
         * Initialize OAuth
         */
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        if (!$request->query->has('client_id')) {
            $this->get('session')->getFlashBag()->set('message', [
                'type' => 'error',
                'message' => 'L\'application externe n\'a pas été trouvée. Vous avez été redirigé vers EtuUTT.',
            ]);

            return $this->redirect($this->generateUrl('homepage'));
        }

        // Find the client
        /** @var OauthClient $client */
        $client = $em->getRepository('EtuCoreApiBundle:OauthClient')->findOneBy([
            'clientId' => $request->query->get('client_id'),
        ]);

        if (!$client) {
            $this->get('session')->getFlashBag()->set('message', [
                'type' => 'error',
                'message' => 'L\'application externe n\'a pas été trouvée. Vous avez été redirigé vers EtuUTT.',
            ]);

            return $this->redirect($this->generateUrl('homepage'));
        }

        // Automatically authenticate user for trusted applications
        $user = null;
        if ($client->getTrusted()) {
            if ($request->query->get('login')) {
                $user = $em->getRepository('EtuUserBundle:User')->findOneBy(['login' => $request->query->get('student_id')]);
            } elseif ($request->query->get('student_id')) {
                $user = $em->getRepository('EtuUserBundle:User')->findOneBy(['studentId' => $request->query->get('student_id')]);
            }

            if (!$user) {
                $this->get('session')->getFlashBag()->set('message', [
                    'type' => 'error',
                    'message' => 'L\'utilisateur n\'a pas pus être identifié.',
                ]);

                return $this->redirect($this->generateUrl('homepage'));
            }
        } else {
            // Check if user is logged in and can use external applications
            if (!$this->isGranted('IS_AUTHENTICATED_FULLY') && !$this->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
                $this->get('session')->getFlashBag()->set('message', [
                    'type' => 'error',
                    'message' => $this->get('translator')->trans('user.api_login.login', ['%name%' => $client->getName()]),
                ]);
            } elseif (!$this->isGranted('ROLE_API_USE')) {
                $this->get('session')->getFlashBag()->set('message', [
                    'type' => 'error',
                    'message' => $this->get('translator')->trans('user.api_login.orga'),
                ]);

                return $this->redirect($this->generateUrl('homepage'));
            }
            $this->denyAccessUnlessGranted('ROLE_API_USE');

            // Get current user
            $user = $this->getUser();
        }

        $requestedScopes = ['public'];

        if ($request->query->has('scopes')) {
            $requestedScopes = array_unique(array_merge($requestedScopes, explode(' ', $request->query->get('scopes'))));
        }

        // Search if user already approved the app
        $authorization = $em->getRepository('EtuCoreApiBundle:OauthAuthorization')->findOneBy([
            'client' => $client,
            'user' => $user,
        ]);

        if ($client->getTrusted()) {
            $authorizationCode = new OauthAuthorizationCode();
            $authorizationCode->setUser($user);
            $authorizationCode->setClient($client);
            $authorizationCode->generateCode();

            // We take all scope decler
            foreach ($authorization->getScopes() as $scope) {
                $authorizationCode->addScope($scope);
            }

            $em->persist($authorizationCode);
            $em->flush();

            if ($request->query->has('state')) {
                return $this->redirect($client->getRedirectUri().'?authorization_code='.$authorizationCode->getCode().'&code='.$authorizationCode->getCode().'&state='.$request->query->get('state', ''));
            }

            return $this->redirect($client->getRedirectUri().'?authorization_code='.$authorizationCode->getCode().'&code='.$authorizationCode->getCode());
        } elseif ($authorization) {
            $authorizationScopes = [];

            foreach ($authorization->getScopes() as $scope) {
                $authorizationScopes[] = $scope->getName();
            }

            // Compare scopes : if more requested, reask authorization, otherwise redirect
            $newScopes = array_diff($requestedScopes, $authorizationScopes);

            if (empty($newScopes)) {
                $authorizationCode = new OauthAuthorizationCode();
                $authorizationCode->setUser($user);
                $authorizationCode->setClient($client);
                $authorizationCode->generateCode();

                foreach ($authorization->getScopes() as $scope) {
                    $authorizationCode->addScope($scope);
                }

                $em->persist($authorizationCode);
                $em->flush();

                if ($request->query->has('state')) {
                    return $this->redirect($client->getRedirectUri().'?authorization_code='.$authorizationCode->getCode().'&code='.$authorizationCode->getCode().'&state='.$request->query->get('state', ''));
                }

                return $this->redirect($client->getRedirectUri().'?authorization_code='.$authorizationCode->getCode().'&code='.$authorizationCode->getCode());
            }
        }

        // Scopes
        $qb = $em->createQueryBuilder();

        /** @var OauthScope[] $scopes */
        $scopes = $qb->select('s')
            ->from('EtuCoreApiBundle:OauthScope', 's')
            ->where($qb->expr()->in('s.name', $requestedScopes))
            ->orderBy('s.weight', 'ASC')
            ->getQuery()
            ->getResult();

        // If the use didn't already approve the app, ask him / her
        $form = $this->createFormBuilder()
            ->add('state', HiddenType::class, ['data' => $request->query->get('state', '')])
            ->add('accept', SubmitType::class, ['label' => 'Oui, accepter', 'attr' => ['class' => 'btn btn-primary', 'value' => '1']])
            ->add('cancel', SubmitType::class, ['label' => 'Non, annuler', 'attr' => ['class' => 'btn btn-default', 'value' => '0']])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $request->request->get('form');

            if (isset($formData['accept'])) {
                // Remove old authorizations
                $em->createQueryBuilder()
                    ->delete()
                    ->from('EtuCoreApiBundle:OauthAuthorization', 'a')
                    ->where('a.client = :client')
                    ->andWhere('a.user = :user')
                    ->setParameter('client', $client->getId())
                    ->setParameter('user', $this->getUser()->getId())
                    ->getQuery()
                    ->execute();

                $authorizationCode = new OauthAuthorizationCode();
                $authorizationCode->setUser($this->getUser());
                $authorizationCode->setClient($client);
                $authorizationCode->generateCode();

                /** @var OauthScope[] $defaultScopes */
                $defaultScopes = $em->getRepository('EtuCoreApiBundle:OauthScope')->findBy(['isDefault' => true]);

                foreach ($defaultScopes as $defaultScope) {
                    $authorizationCode->addScope($defaultScope);
                }

                foreach ($scopes as $scope) {
                    if (!$scope->getIsDefault()) {
                        $authorizationCode->addScope($scope);
                    }
                }

                $em->persist($authorizationCode);

                // Persist authorization to not ask anymore
                $em->persist(OauthAuthorization::createFromAuthorizationCode($authorizationCode));

                $em->flush();

                return $this->redirect($client->getRedirectUri().'?authorization_code='.$authorizationCode->getCode().'&code='.$authorizationCode->getCode().'&state='.($formData['state'] ?? ''));
            }

            return $this->redirect($client->getRedirectUri().'?error=authentification_canceled&error_message=L\'utilisateur a annulé l\'authentification.');
        }

        return [
            'client' => $client,
            'user' => $client->getUser(),
            'scopes' => $scopes,
            'form' => $form->createView(),
        ];
    }

    /**
     * Create an `access_token` to use the API.
     *
     * There are three methods to create an `access_token`:
     *
     * - `authorization_code`
     * - `refresh_token`
     * - `client_credentials`
     *
     * These methods are called the **the grant types**. The required parameters of the endpoint depends on the chosen
     * grant type.
     *
     * @ApiDoc(
     *   section = "OAuth",
     *   description = "Create a OAuth access token using given grant_type",
     *   parameters = {
     *      {
     *          "name" = "grant_type",
     *          "required" = true,
     *          "dataType" = "string",
     *          "description" = "The grant type to use to create the access_token."
     *      },
     *      {
     *          "name" = "scopes",
     *          "required" = false,
     *          "dataType" = "string",
     *          "description" = "List of the scopes you need for the token, separated by spaces, for instance: `public private_user_account`. If not provided, grant only access to public scope."
     *      },
     *      {
     *          "name" = "code",
     *          "required" = false,
     *          "dataType" = "string",
     *          "description" = "The authorization_code generated with /api/oauth/authorize. Required if grant_type == 'authorization_code'."
     *      },
     *      {
     *          "name" = "refresh_token",
     *          "required" = false,
     *          "dataType" = "string",
     *          "description" = "The refresh_token provided on the first access_token retrieval. Required if grant_type == 'refresh_token'."
     *      },
     *      {
     *          "name" = "client_id",
     *          "required" = false,
     *          "dataType" = "string",
     *          "description" = "Your Client ID (given in your developper panel). Required if not using Basic Authentification."
     *      },
     *      {
     *          "name" = "client_secret",
     *          "required" = false,
     *          "dataType" = "string",
     *          "description" = "Your Client Secret Token (given in your developper panel). Required if not using Basic Authentification."
     *      }
     *   }
     * )
     *
     * @Route("/token", name="oauth_token")
     * @Method("POST")
     */
    public function tokenAction(Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        $clientId = $request->get('client_id', $request->server->get('PHP_AUTH_USER'));
        $clientSecret = $request->get('client_secret', $request->server->get('PHP_AUTH_PW'));

        /** @var OauthClient $client */
        $client = $em->getRepository('EtuCoreApiBundle:OauthClient')->findOneBy([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
        ]);

        if (!$client) {
            return $this->format([
                'error' => 'invalid_client',
                'error_message' => 'Client credentials are invalid',
            ], 401, [], $request);
        }

        /** @var OauthServer $server */
        $server = $this->get('etu.oauth.server');

        $request->attributes->set('_oauth_client', $client);

        $grantType = $request->request->get('grant_type');

        try {
            $token = $server->createToken($grantType, $request);
        } catch (\RuntimeException $exception) {
            return $this->format([
                'error' => 'grant_type_error',
                'error_message' => $exception->getMessage(),
                'received_request' => $request->request->all(),
            ], 400, [], $request);
        }

        return $this->format($server->formatToken($grantType, $token), 200, [], $request);
    }
}
