<?php

namespace Etu\Core\UserBundle\Sync\Iterator\Element;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Etu\Core\UserBundle\Entity\User;

/**
 * Element to remove from database
 */
class ElementToRemove
{
	/**
	 * @var User
	 */
	protected $element;

	/**
	 * @var Registry
	 */
	protected $doctrine;

	/**
	 * @param Registry $doctrine
	 * @param User $element
	 * @throws \RuntimeException
	 */
	public function __construct(Registry $doctrine, $element)
	{
		if (! $element instanceof User) {
			if (is_object($element)) {
				$type = get_class($element);
			} else {
				$type = gettype($element);
			}

			throw new \RuntimeException(sprintf(
				'EtuUTT synchonizer can only remove/keep User objects (%s given)', $type
			));
		}

		$this->element = $element;
		$this->doctrine = $doctrine;
	}

	/**
	 * Remove the user from the database
	 */
	public function remove()
	{
		$this->doctrine->getManager()->remove($this->element);
	}

	/**
	 * Keep the user in the database and set a password for external connexion
	 */
	public function keep($encryptedPassword)
	{
		$this->element->setPassword($encryptedPassword);
		$this->element->setKeepActive(true);

		$this->doctrine->getManager()->persist($this->element);
	}

	/**
	 * @return \Etu\Core\UserBundle\Entity\User
	 */
	public function getElement()
	{
		return $this->element;
	}
}