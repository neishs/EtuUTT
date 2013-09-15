<?php

namespace Etu\Core\UserBundle\Controller;

use Doctrine\ORM\EntityManager;

use Etu\Core\CoreBundle\Framework\Definition\Controller;
use Etu\Core\UserBundle\Entity\Course;
use Etu\Core\UserBundle\Schedule\Helper\ScheduleBuilder;

use Sabre\VObject\Component\VCalendar;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;

class ScheduleController extends Controller
{
	/**
	 * @Route("/schedule/edit", name="user_schedule_edit")
	 * @Template()
	 */
	public function scheduleEditAction()
	{
		if (! $this->getUserLayer()->isStudent()) {
			return $this->createAccessDeniedResponse();
		}

		/** @var $em EntityManager */
		$em = $this->getDoctrine()->getManager();

		/** @var $myCourses Course[] */
		$courses = $em->getRepository('EtuUserBundle:Course')->findByUser($this->getUser());

		// Builder to create the schedule
		$builder = new ScheduleBuilder();

		foreach ($courses as $course) {
			$builder->addCourse($course);
		}

		return array(
			'courses' => $builder->build()
		);
	}

	/**
	 * @Route("/schedule/print", name="user_schedule_print")
	 * @Template()
	 */
	public function schedulePrintAction()
	{
		if (! $this->getUserLayer()->isStudent()) {
			return $this->createAccessDeniedResponse();
		}

		/** @var $em EntityManager */
		$em = $this->getDoctrine()->getManager();

		/** @var $myCourses Course[] */
		$courses = $em->getRepository('EtuUserBundle:Course')->findByUser($this->getUser());

		// Builder to create the schedule
		$builder = new ScheduleBuilder();

		foreach ($courses as $course) {
			$builder->addCourse($course);
		}

		return array(
			'courses' => $builder->build()
		);
	}

	/**
	 * @Route("/schedule/course/{id}", name="schedule_course")
	 * @Template()
	 */
	public function courseAction(Course $course)
	{
		if (! $this->getUserLayer()->isStudent()) {
			return $this->createAccessDeniedResponse();
		}

		/** @var $em EntityManager */
		$em = $this->getDoctrine()->getManager();

		$students = $em->createQueryBuilder()
			->select('c, u')
			->from('EtuUserBundle:Course', 'c')
			->leftJoin('c.user', 'u')
			->where('c.uv = :uv')
			->andWhere('c.start = :start')
			->andWhere('c.end = :end')
			->andWhere('c.week = :week')
			->andWhere('c.room = :room')
			->setParameter('uv', $course->getUv())
			->setParameter('start', $course->getStart())
			->setParameter('end', $course->getEnd())
			->setParameter('week', $course->getWeek())
			->setParameter('room', $course->getRoom())
			->orderBy('u.lastName', 'ASC')
			->getQuery()
			->getResult();

		return array(
			'course' => $course,
			'students' => $students
		);
	}

	/**
	 * @Route("/schedule/export", name="user_schedule_export")
	 * @Template()
	 */
	public function exportAction()
	{
		if (! $this->getUserLayer()->isStudent()) {
			return $this->createAccessDeniedResponse();
		}

		/** @var $em EntityManager */
		$em = $this->getDoctrine()->getManager();

		/** @var $courses Course[] */
		$courses = $em->getRepository('EtuUserBundle:Course')->findByUser($this->getUser());

		$vcalendar = new VCalendar();

		foreach ($courses as $course) {
			$day = new \DateTime('last '.$course->getDay());

			$start = clone $day;
			$time = explode(':', $course->getStart());
			$start->setTime($time[0], $time[1]);

			$end = clone $day;
			$time = explode(':', $course->getEnd());
			$end->setTime($time[0], $time[1]);

			$vcalendar->add('VEVENT', [
				'SUMMARY' => $course->getUv().' - '.$course->getType().' - '.$course->getRoom(),
				'DTSTART' => $start,
				'DTEND' => $end,
				'RRULE' => 'FREQ=WEEKLY;INTERVAL=1',
				'LOCATION' => $course->getRoom(),
				'CATEGORIES' => $course->getType(),
			]);
		}

		$response = new Response($vcalendar->serialize());

		$response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
		$response->headers->set('Content-Disposition', 'attachment; filename="etuutt_schedule.ics"');

		return $response;
	}

	/**
	 * @Route("/schedule/{day}", defaults={"day" = "current"}, name="user_schedule")
	 * @Template()
	 */
	public function scheduleAction($day = 'current')
	{
		if (! $this->getUserLayer()->isStudent()) {
			return $this->createAccessDeniedResponse();
		}

		/** @var $em EntityManager */
		$em = $this->getDoctrine()->getManager();

		/** @var $myCourses Course[] */
		$courses = $em->getRepository('EtuUserBundle:Course')->findByUser($this->getUser());

		// Builder to create the schedule
		$builder = new ScheduleBuilder();

		foreach ($courses as $course) {
			$builder->addCourse($course);
		}

		$days = array(
			Course::DAY_MONDAY, Course::DAY_TUESDAY, Course::DAY_WENESDAY,
			Course::DAY_THURSDAY, Course::DAY_FRIDAY, Course::DAY_SATHURDAY
		);

		if (! in_array($day, $days)) {
			if (date('w') == 0) { // Sunday
				$day = Course::DAY_MONDAY;
			} else {
				$day = $days[date('w') - 1];
			}
		}

		return array(
			'courses' => $builder->build(),
			'currentDay' => $day,
		);
	}
}
