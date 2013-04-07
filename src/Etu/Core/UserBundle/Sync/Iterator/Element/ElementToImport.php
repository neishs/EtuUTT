<?php

namespace Etu\Core\UserBundle\Sync\Iterator\Element;

use Doctrine\Bundle\DoctrineBundle\Registry;

use Imagine\Gd\Image;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;

use Etu\Core\UserBundle\Ldap\Model\Organization;
use Etu\Core\UserBundle\Ldap\Model\User;

/**
 * LDAP element to import in database
 */
class ElementToImport
{
	/**
	 * @var Organization|User
	 */
	protected $element;

	/**
	 * @var Registry
	 */
	protected $doctrine;

	/**
	 * @param Registry $doctrine
	 * @param User|Organization $element
	 * @throws \RuntimeException
	 */
	public function __construct(Registry $doctrine, $element)
	{
		if (! $element instanceof User && ! $element instanceof Organization) {
			if (is_object($element)) {
				$type = get_class($element);
			} else {
				$type = gettype($element);
			}

			throw new \RuntimeException(sprintf(
				'EtuUTT synchonizer can only import User and Organization objects (%s given)', $type
			));
		}

		$this->element = $element;
		$this->doctrine = $doctrine;
	}

	/**
	 * Import the element in the database
	 */
	public function import()
	{
		if ($this->element instanceof User) {
			return $this->importUser();
		} else {
			return $this->importOrganization();
		}
	}

	/**
	 * Import a user in the database
	 */
	protected function importUser($flush = false)
	{
		$imagine = new Imagine();
		$webDirectory = __DIR__.'/../../../../../../../web';

		$avatar = $this->element->getLogin().'.jpg';

		if (! file_exists($webDirectory.'/photos/'.$this->element->getLogin().'.jpg')) {
			// Resize photo
			try {
				$image = $imagine->open('http://local-sig.utt.fr/Pub/trombi/individu/'.$this->element->getStudentId().'.jpg');

				$image->copy()
					->thumbnail(new Box(200, 200), Image::THUMBNAIL_OUTBOUND)
					->save($webDirectory.'/photos/'.$this->element->getLogin().'.jpg');
			} catch (\Exception $e) {
				$avatar = 'default-avatar.png';
			}
		}

		$user = new \Etu\Core\UserBundle\Entity\User();
		$user->setAvatar($avatar);
		$user->setLogin($this->element->getLogin());
		$user->setFullName($this->element->getFullName());
		$user->setFirstName($this->element->getFirstName());
		$user->setLastName($this->element->getLastName());
		$user->setFiliere($this->element->getFiliere());
		$user->setFormation(ucfirst(strtolower($this->element->getFormation())));
		$user->setNiveau($this->element->getNiveau());
		$user->setMail($this->element->getMail());
		$user->setPhoneNumber($this->element->getPhoneNumber());
		$user->setRoom($this->element->getRoom());
		$user->setStudentId($this->element->getStudentId());
		$user->setTitle($this->element->getTitle());
		$user->setLdapInformations($this->element);
		$user->setIsStudent($this->element->getIsStudent());
		$user->setKeepActive(false);

		$this->doctrine->getManager()->persist($user);

		if ($flush) {
			$this->doctrine->getManager()->flush();
		}

		return $user;
	}

	/**
	 * Import an organization in the database
	 */
	protected function importOrganization($flush = false)
	{
		$orga = new \Etu\Core\UserBundle\Entity\Organization();
		$orga->setLogo('default-logo.png');
		$orga->setLogin($this->element->getLogin());
		$orga->setContactMail($this->element->getMail());
		$orga->setName($this->element->getFullName());

		$this->doctrine->getManager()->persist($orga);

		if ($flush) {
			$this->doctrine->getManager()->flush();
		}

		return $orga;
	}

	/**
	 * @return \Etu\Core\UserBundle\Ldap\Model\Organization|\Etu\Core\UserBundle\Ldap\Model\User
	 */
	public function getElement()
	{
		return $this->element;
	}
}