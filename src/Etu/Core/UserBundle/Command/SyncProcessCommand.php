<?php

namespace Etu\Core\UserBundle\Command;

use Doctrine\ORM\EntityManager;
use Etu\Core\UserBundle\Command\Util\ProgressBar;
use Etu\Core\UserBundle\Entity\User;
use Etu\Core\UserBundle\Sync\Iterator\Element\ElementToImport;
use Etu\Core\UserBundle\Sync\Iterator\Element\ElementToRemove;
use Etu\Core\UserBundle\Sync\Iterator\Element\ElementToUpdate;
use Etu\Core\UserBundle\Sync\Iterator\ImportIterator;
use Etu\Core\UserBundle\Sync\Iterator\RemoveIterator;
use Etu\Core\UserBundle\Sync\Iterator\UpdateIterator;
use Etu\Core\UserBundle\Sync\Synchronizer;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class SyncProcessCommand extends ContainerAwareCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setName('etu:users:sync')
            ->setDescription('Synchronize users with the LDAP');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        $output->writeln('
	Welcome to the EtuUTT users manager

This command helps you to synchronise database with LDAP.

For each user that don\'t exit anymore in the LDAP, The command will keep them,
but if they want to connect, you will have to set a password for them.
');

        $container = $this->getContainer();

        // Users
        $output->writeln('Finding users differences ...');

        /** @var $synchronizer Synchronizer */
        $synchronizer = $container->get('etu.user.sync');

        $output->writeln('----------------------------------------');

        /** @var $usersImportIterator ImportIterator */
        $usersImportIterator = $synchronizer->createUsersSyncProcess()->getImportIterator();

        /** @var $usersRemoveIterator RemoveIterator */
        $usersRemoveIterator = $synchronizer->createUsersSyncProcess()->getRemoveIterator();

        /** @var $usersUpdateIterator UpdateIterator */
        $usersUpdateIterator = $synchronizer->createUsersSyncProcess()->getUpdateIterator();

        $output->writeln(sprintf('%s user(s) to import from LDAP', $usersImportIterator->count()));
        $output->writeln(sprintf('%s user(s) to remove/keep in database', $usersRemoveIterator->count()));
        $output->writeln(sprintf('%s user(s) to compare with the LDAP', $usersUpdateIterator->count()));

        $output->write("\n");

        $startNow = $helper->ask($input, $output, new Question('Start the synchronization now (y/n) [y]? ', 'y')) == 'y';

        if (!$startNow) {
            $output->writeln("Aborted.\n");

            return;
        }

        // Import users
        if ($usersImportIterator->count() > 0) {
            $output->write("\n");
            $output->writeln('Importing users ...');

            /** @var $em EntityManager */
            $em = $container->get('doctrine')->getManager();

            $bde = $em->createQueryBuilder()
                ->select('o')
                ->from('EtuUserBundle:Organization', 'o')
                ->where('o.login = :login')
                ->setParameter('login', 'bde')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            $bar = new ProgressBar('%fraction% [%bar%] %percent%', '=>', ' ', 80, $usersImportIterator->count());
            $bar->update(0);
            $i = 1;

            /** @var $user ElementToImport */
            foreach ($usersImportIterator as $user) {
                $user->import(false, $bde);
                $bar->update($i);
                ++$i;
            }

            $output->write("\n");
            $output->writeln('Saving in database ...');
            $container->get('doctrine')->getManager()->flush();
        }

        // Updating users
        if ($usersUpdateIterator->count() > 0) {
            $output->write("\n");
            $output->writeln('Comparing users ...');

            $bar = new ProgressBar('%fraction% [%bar%] %percent%', '=>', ' ', 80, $usersUpdateIterator->count());
            $bar->update(0);
            $i = 1;
            $countPersisted = 0;

            /** @var $user ElementToUpdate */
            foreach ($usersUpdateIterator as $user) {
                $persisted = $user->update();

                if ($persisted) {
                    ++$countPersisted;
                }

                $bar->update($i);
                ++$i;
            }

            $output->write("\n");
            $output->writeln('Saving in database ...');
            $output->write("\n");
            $output->writeln($countPersisted.' user(s) updated');
            $container->get('doctrine')->getManager()->flush();
        }

        // Remove users
        $output->write("\n\n");

        if ($usersRemoveIterator->count() > 0) {
            if ($usersRemoveIterator->count() == 1) {
                $item = $usersRemoveIterator->get(0);

                $output->writeln(sprintf(
                    'There is 1 user (`%s`) which is not in the LDAP but in the database.',
                    $item->getElement()->getLogin()
                ));
                $item->remove();
                $output->writeln("\n1 flagged as not in LDAP anymore");
            } else {
                $logins = [];

                /** @var $item ElementToRemove */
                foreach ($usersRemoveIterator as $item) {
                    $logins[] = $item->getElement()->getLogin();
                }

                if ($usersRemoveIterator->count() <= 20) {
                    $output->writeln(sprintf(
                        'There are %s users which are not in the LDAP but in the database (`%s`).',
                        $usersRemoveIterator->count(), implode('`, `', $logins)
                    ));
                } else {
                    $output->writeln(sprintf(
                        'There are %s users which are not in the LDAP but in the database.',
                        $usersRemoveIterator->count()
                    ));
                }

                // Flag them as not in LDAP anymore
                $remove = $usersRemoveIterator->all();
                foreach ($remove as $item) {
                    $item->remove();
                }

                $container->get('doctrine')->getManager()->flush();

                $output->writeln(sprintf("\n%s user(s) flagged as not in LDAP anymore", count($remove)));
            }
        }

        $output->writeln("Done.\n");
    }
}
