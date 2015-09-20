<?php

/*
 * PHP interface to the programming game Realtimebattle
 *
 * See: http://realtimebattle.sourceforge.net/
 * for credits, documentation, downloads etc.
 * (Thanks guys for all the fun!)
 *
 * About this file
 * Author:	Leo Noordergraaf
 * Created:	September 2015
 * Licence:	GNU General Public License v3 or later
 * 			See http://www.gnu.org/copyleft/gpl.html
*/

class Realtimebattle
{
	// half a circle and full circle in radians
	const PI  = 3.14159265358979323846;	// pi     (half circle)
	const PI2 = 6.28318530717958647693; // 2pi    (full circle)
	const RAD = 57.2957795130823208768; // 180/pi (1 radian expressed in degrees)
	const DEG = 0.01745329251994329577; // pi/180 (1 degree expressed in radians)

	// Warnings
	const WRN_UNKNOWN = 0;
	const WRN_TIME_LOW = 1;
	const WRN_ILLEGAL_MESSAGE = 2;
	const WRN_UNKNOWN_OPTION = 3;
	const WRN_OBSOLETE_KEYWORD = 4;
	const WRN_NO_NAME = 5;
	const WRN_NO_COLOUR = 6;

	// RobotOption options
	const SEND_SIGNAL = 0;
	const SEND_ROTATION_REACHED = 1;
	const SIGNAL = 2;
	const USE_NON_BLOCKING = 3;

	// $what value for RotateX() functions (NB: bitmap!)
	const ROTATE_ROBOT = 1;
	const ROTATE_CANNON = 2;
	const ROTATE_RADAR = 4;

	// $method in __construct one of:
	// NB: only METHOD_BLOCKING currently implemented
	const METHOD_BLOCKING = 1;
	// const METHOD_SIGNAL = 2;
	// const METHOD_SELECT = 3;	// perhaps impossible in PHP?

	// Game options
	const ROBOT_MAX_ROTATE = 0;
	const ROBOT_CANNON_MAX_ROTATE = 1;
	const ROBOT_RADAR_MAX_ROTATE = 2;
	const ROBOT_MAX_ACCELERATION = 3;
	const ROBOT_MIN_ACCELERATION = 4;
	const ROBOT_START_ENERGY = 5;
	const ROBOT_MAX_ENERGY = 6;
	const ROBOT_ENERGY_LEVELS = 7;
	const SHOT_SPEED = 8;
	const SHOT_MIN_ENERGY = 9;
	const SHOT_MAX_ENERGY = 10;
	const SHOT_ENERGY_INCREASE = 11;
	const TIMEOUT = 12;
	const DEBUG_LEVEL = 13;
	const SEND_ROBOT_COORDINATES = 14;

	// $object / $type values
	const NOOBJECT = -1;
	const ROBOT = 0;
	const SHOT = 1;
	const WALL = 2;
	const COOKIE = 3;
	const MINE = 4;

	// class initiation parameters
	protected $connection_method;
	protected $name;
	protected $team;
	protected $home_colour;
	protected $away_colour;

	// program/game state variables
	protected $active;
	protected $alive;
	protected $options = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0);

	// robot state
	protected $robot = array(
		'time' => 0,
		'energy' => 0,
		'x' => 0,
		'y' => 0,
		'angle' => 0,
		'speed' => 0,
		'cannon' => 0
	);

	// discovered objects
	// for each object class, note the position and,
	// in case it is a robot, note its energy level and whether it is a teammate
	protected $radar = array(
		array('x' => 0, 'y' => 0, 'energy' => 0, 'teammate' => 0), # ROBOT
		array('x' => 0, 'y' => 0), # SHOT
		array('x' => 0, 'y' => 0), # WALL
		array('x' => 0, 'y' => 0), # COOKIE
		array('x' => 0, 'y' => 0), # MINE
	);

	// true when game state between Initialize and ExitRobot
	// i.e. our robot is communicating with the server
	public function isActive()
	{
		return $this->active;
	}

	// true when game state between GameStarts and GameFinishes
	// i.e. our robot is participating in a battle
	public function isAlive()
	{
		return $this->alive;
	}

	// returns the value of the requested game option
	public function getOption($option)
	{
		return $this->options[$option];
	}

	// are we in debug mode?
	public function isDebug()
	{
		return intval($this->options[self::DEBUG_LEVEL]) == 5;
	}

	// returns the most up-to-date robot state
	public function getState()
	{
		return $this->robot;
	}

	// returns the most recent scan for the requested object type
	public function getRadar($object)
	{
		return $this->radar[$object];
	}

	// should be __construct but was too impatient to get it right...
	public function start($name, $home, $away, $team = '', $method = self::METHOD_BLOCKING)
	{
		$this->name = $name;
		$this->team = $team;
		$this->home_colour = $home;
		$this->away_colour = $away;
		$this->connection_method = $method;
	}

	// enter the game loop
	public function play()
	{
		$this->active = true;
		$this->alive = false;

		// tell server how we want to communicate
		// must be the very first thing we send (obviously)
		if ($this->connection_method == self::METHOD_BLOCKING) {
			$this->Option(self::USE_NON_BLOCKING, 0);
		}

		// for as long as the server wants us
		while($this->active) {
			// get a status update
			$line = fgets(STDIN);
			//fputs(STDOUT, "Print --> $line");

			// if the programmer wants to do it all...
			if ($this->RawData($line)) {
				continue;
			}

			// status updates are single words separated by spaces
			$parts = explode(" ", $line);

			// determine parameters and call handler for each kind of update
			switch($parts[0]) {
				case "Initialize":		$this->myInitialize($parts[1]);							break;
				case "YourName":		$this->YourName($parts[1]);								break;
				case "YourColour":		$this->YourColour($parts[1]);							break;
				case "GameOption":		$this->myGameOption($parts[1], $parts[2]);				break;
				case "GameStarts":		$this->myGameStarts();									break;
				case "Radar":			$this->myRadar($parts[1], $parts[2], $parts[3]);		break;
				case "Info":			$this->myInfo($parts[1], $parts[2], $parts[3]);			break;
				case "Coordinates":		$this->myCoordinates($parts[1], $parts[2], $parts[3]);	break;
				case "RobotInfo":		$this->myRobotInfo($parts[1], $parts[2]);				break;
				case "RotationReached":	$this->RotationReached($parts[1]);						break;
				case "Energy":			$this->myEnergy($parts[1]);								break;
				case "RobotsLeft":		$this->RobotsLeft($parts[1]);							break;
				case "Collision":		$this->Collision($parts[1], $parts[2]);					break;
				case "Warning":			$this->Warning($parts[1], $parts[2]);					break;
				case "Dead":			$this->myDead();										break;
				case "GameFinishes":	$this->GameFinishes();									break;
				case "ExitRobot":		$this->myExitRobot();									break;
			}
		}
		// guess we're done
	}

	// send a command to the server
	// extracted instead of fprintf to be able to debug
	private function send($line)
	{
		//fputs(STDOUT, "Print <-- $line");
		fputs(STDOUT, $line);
	}

	// Public handler functions, the desired ones can be overridden, the rest ignored
	public function RawData($line) { return false; }
	public function Initialize($first) { return false; }
	public function YourName($name) { return false; }
	public function YourColour($colour) { return false; }
	public function GameOption($option, $value) { return false; }
	public function GameStarts() { return false; }
	public function Radar($distance, $object, $object_angle) { return false; }
	public function Info($time, $speed, $cannon_angle) { return false; }
	public function Coordinates($x, $y, $driving_angle) { return false; }
	public function RobotInfo($energy, $teammate) { return false; }
	public function RotationReached($what) { return false; }
	public function Energy($energy) { return false; }
	public function RobotsLeft($count) { return false; }
	public function Collision($object, $angle) { return false; }
	public function Warning($type, $message) { return false; }
	public function Dead() { return false; }
	public function GameFinishes() { return false; }
	public function ExitRobot() { return false; }

	// Internal handler functions.
	// These handlers maintain the state of the system and pass
	// the call on to the public handler functions.
	// This lets you choose between handling yourself our querying this class

	// Initialize, provide Name and Colours to the server
	// When the public Initialize() function returns true, it is assumed it took
	// care of everything.
	private function myInitialize($first)
	{
		if (!$this->Initialize($first)) {
			if ($first) {
				$this->Name($this->name);
				$this->Colour($this->home_colour, $this->away_colour);
			}
		}
	}

	// Store the game option (options are sent only once during a game)
	private function myGameOption($option, $value)
	{
		$this->options[$option] = $value;
		$this->GameOption($option, $value);
	}

	// Entering a battle
	private function myGameStarts()
	{
		$this->alive = true;
		$this->GameStarts();
	}

	// Determine and store object position based on robot's position and angle.
	// Storing distance and angle won't work since the robot is not static (or
	// we should store the robot's position with the sighting)
	private function myRadar($distance, $object, $object_angle)
	{
		$this->Radar($distance, $object, $object_angle);
	}

	// Provide additional info about the robot state:
	// game time, speed, angle of cannon relative to robot in radians
	private function myInfo($time, $speed, $cannon_angle)
	{
		$this->robot['time'] = $time;
		$this->robot['speed'] = $speed;
		$this->robot['cannon'] = $cannon_angle;
		$this->Info($time, $speed, $cannon_angle);
	}

	// Provide primary info about the robot state:
	// position and angle of direction in radians
	private function myCoordinates($x, $y, $driving_angle)
	{
		$this->robot['x'] = $x;
		$this->robot['y'] = $y;
		$this->robot['angle'] = $driving_angle;
		$this->Coordinates($x, $y, $driving_angle);
	}

	// Only received when the Radar sighted another robot,
	// report its energy and whether it is a teammate
	private function myRobotInfo($energy, $teammate)
	{
		$this->radar[self::ROBOT]['energy'] = $energy;
		$this->radar[self::ROBOT]['teammate'] = $teammate;
		$this->RobotInfo($energy, $teammate);
	}

	// Report my energy level
	private function myEnergy($energy)
	{
		$this->robot['energy'] = $energy;
		$this->Energy($energy);
	}

	// Notify the program that this battle is lost
	private function myDead()
	{
		$this->alive = false;
		$this->Dead();
	}

	// Exit the game
	private function myExitRobot()
	{
		$this->active = false;
		$this->ExitRobot();
	}

	// Next are the commands a robot can send to the server

	// Set an option, see the list of constants and the documentation
	// for values and impact
	public function Option($option, $value)
	{
		if ($this->isActive()) {
			$this->send(sprintf("RobotOption %d %d\n", $option, $value));
		}
	}

	// Declare the name of this robot
	public function Name($name)
	{
		if ($this->isActive()) {
			if ($this->team != '') {
				$this->send(sprintf("Name %s Team: %s\n", $name, $team));
			} else {
				$this->send(sprintf("Name %s\n", $name));
			}
		}
	}

	// Give my preferred colours (no clue when $away is used)
	public function Colour($home, $away)
	{
		if ($this->isActive()) {
			$this->send(sprintf("Colour %s %s\n", $home, $away));
		}
	}

	// Rotate robot, cannon, radar or any combination of these
	// with the turn speed in radians/second
	public function Rotate($what, $velocity)
	{
		if ($this->isAlive()) {
			$this->send(sprintf("Rotate %d %f\n", $what, $velocity));
		}
	}

	// Rotate cannon, radar in any combination to $angle (radians) relative to
	// the robot with turn speed $velocity in radians/second
	public function RotateTo($what, $velocity, $angle)
	{
		if ($this->isAlive()) {
			$this->send(sprintf("RotateTo %d %f %f\n", $what, $velocity, $angle));
		}
	}

	// As Rotate but $angle gives the destination angle in radians
	public function RotateAmount($what, $velocity, $angle)
	{
		if ($this->isAlive()) {
			$this->send(sprintf("RotateAmount %d %f %f\n", $what, $velocity, $angle));
		}
	}

	// Rotate cannon and/or radar left to right and back, with
	// velocity in radians/second, $left and $right are the angles
	// relative to the robot between which to sweep
	public function Sweep($what, $velocity, $left, $right)
	{
		if ($this->isAlive()) {
			$this->send(sprintf("Sweep %d %f %f %f\n", $what, $velocity, $left, $right));
		}
	}

	// Increase speed
	public function Accelerate($value)
	{
		if ($this->isAlive()) {
			$this->send(sprintf("Accelerate %f\n", $value));
		}
	}

	// Reduce speed
	public function Brake($portion)
	{
		if ($this->isAlive()) {
			$this->send(sprintf("Brake %f\n", $portion));
		}
	}

	// Shoot the cannon
	public function Shoot($energy)
	{
		if ($this->isAlive()) {
			$this->send(sprintf("Shoot %f\n", $energy));
		}
	}

	// Write a message to the console
	public function Write($message)
	{
		if ($this->isActive()) {
			$this->send(sprintf("Print %s\n", $message));
		}
	}

	// Write a message when in debug mode
	public function Debug($message)
	{
		if ($this->isDebug()) {
			$this->send(sprintf("Debug %s\n", $message));
		}
	}

	// Draw a line when in debug mode, angles and distances relative to robot
	public function DebugLine($angle1, $distance1, $angle2, $distance2)
	{
		if ($this->isDebug()) {
			$this->send(sprintf("DebugLine %f %f %f %f\n", $angle1, $distance1, $angle2, $distance2));
		}
	}

	// Draw a circle when in debug mode, angles and distances relative to robot
	public function DebugCircle($angle, $distance, $radius)
	{
		if ($this->isDebug()) {
			$this->send(sprintf("DebugCircle %f %f %f\n", $angle, $distance, $radius));
		}
	}
}
