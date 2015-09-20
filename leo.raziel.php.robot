#!/usr/bin/php
<?php

/*
 * Razziel: a Realtimebattle robot reimplementing the raziel.py.robot example
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

require "realtimebattle.php";

class Game extends Realtimebattle
{
	# indexed by $type parameter of Warning($type, $message)
	public $warning_type = array(
		"Unknown message",
		"Process time low",
		"Message sent in illegal state",
		"Unknown option",
		"Obsolete keyword",
		"Name not given",
		"Colour not given"
	);

	# indexed by $type parameter of GameOption($type, $value)
	public $game_option_type = array(
		"Robot max rotate",
		"Robot cannon max rotate",
		"Robot radar max rotate",
		"Robot max acceleration",
		"Robot min acceleration",
		"Robot start energy",
		"Robot max energy",
		"Robot energy levels",
		"Shot speed",
		"Shot min energy",
		"Shot max energy",
		"Shot energy increase speed",
		"Timeout",
		"Debug level",
		"Send robot coordinates"
	);

	# indexed by $object / $type values
	public $object_type = array(
		"Robot",
		"Shot",
		"Wall",
		"Cookie",
		"Mine"
	);

	public function RawData($line)
	{
		if ($this->isAlive()) {
			$this->Accelerate(0.5);
			$this->Rotate(7, 3);
			for ($i = 0; $i < 10; $i++)
				$this->Shoot(10);
		}
		return false;
	}

	public function Warning($type, $message)
	{
		$this->Debug(sprintf("Warning: %s: %s", $this->warning_type[$type], $message));
	}

	public function GameOption($type, $value)
	{
		$this->Debug(sprintf("Game option: %s: %f", $this->game_option_type[$type], $value));
	}

	public function Radar($distance, $object, $object_angle)
	{
		if ($object != parent::WALL && $object != parent::SHOT) {
			$this->Debug(sprintf("Radar: %s (%d) at %f rad and %f distance", $this->object_type[$object], $object, $object_angle, $distance));
			$this->DebugLine(0, 0, $object_angle, $distance);
		}
	}
}

$game = new Game();
$game->start("razziel", "ff0000", "00ff00");
$game->play();