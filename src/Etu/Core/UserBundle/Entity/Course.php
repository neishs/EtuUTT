<?php

namespace Etu\Core\UserBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * User course
 *
 * @ORM\Table(name="etu_users_courses")
 * @ORM\Entity
 */
class Course
{
	const WEEK_ALL = 'T';
	const WEEK_A = 'A';
	const WEEK_B = 'B';

	const DAY_MONDAY = 'monday';
	const DAY_TUESDAY = 'tuesday';
	const DAY_WENESDAY = 'wenesday';
	const DAY_THURSDAY = 'thursday';
	const DAY_FRIDAY = 'friday';
	const DAY_SATHURDAY = 'sathurday';
	const DAY_SUNDAY = 'sunday';

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

	/**
	 * @var User $user
	 *
	 * @ORM\ManyToOne(targetEntity="User")
	 * @ORM\JoinColumn()
	 */
    protected $user;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="day", type="string", length=20)
	 */
	protected $day;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="start", type="string", length=10)
	 */
	protected $start;

    /**
     * @var string
     *
     * @ORM\Column(name="end", type="string", length=10)
     */
    protected $end;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="week", type="string", length=10)
	 */
	protected $week;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="uv", type="string", length=50)
	 */
	protected $uv;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="type", type="string", length=50)
	 */
	protected $type;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="room", type="string", length=50)
	 */
	protected $room;

	/**
	 * Constructor
 	 */
	public function __construct()
	{
		$this->room = 'NC';
	}

	/**
	 * @param string $end
	 * @return Course
	 */
	public function setEnd($end)
	{
		$this->end = $end;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getEnd()
	{
		return $this->end;
	}

	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @param string $room
	 * @return Course
	 */
	public function setRoom($room)
	{
		$this->room = $room;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRoom()
	{
		return $this->room;
	}

	/**
	 * @param string $day
	 * @return Course
	 */
	public function setDay($day)
	{
		$this->day = $day;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getDay()
	{
		return $this->day;
	}

	/**
	 * @param string $start
	 * @return Course
	 */
	public function setStart($start)
	{
		$this->start = $start;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getStart()
	{
		return $this->start;
	}

	/**
	 * @param string $week
	 * @return Course
	 */
	public function setWeek($week)
	{
		$this->week = $week;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getWeek()
	{
		return $this->week;
	}

	/**
	 * @param string $uv
	 * @return Course
	 */
	public function setUv($uv)
	{
		$this->uv = $uv;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getUv()
	{
		return $this->uv;
	}

	/**
	 * @param string $type
	 * @return Course
	 */
	public function setType($type)
	{
		$this->type = $type;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * @param \Etu\Core\UserBundle\Entity\User $user
	 * @return Course
	 */
	public function setUser($user)
	{
		$this->user = $user;

		return $this;
	}

	/**
	 * @return \Etu\Core\UserBundle\Entity\User
	 */
	public function getUser()
	{
		return $this->user;
	}
}