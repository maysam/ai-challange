<?php
	function tag($ant)
	{
		list($aRow, $aCol) = $ant;
		return $aRow.'-'.$aCol; 
	}
	function untag($inant)
	{
		$outant = explode('-',$inant);
		return $outant;
	}
	function move($from,$to)
	{
		return $from.'&rarr;'.$to;
	}
	function unmove($move)
	{
		return explode('&rarr;', $move);
	}
define('MY_HILL', 11);
define('ENEMY_HILL', 12);
define('MY_ANT', 1);
define('ENEMY_ANT', 2);
define('DEAD', -1);
define('LAND', -2);
define('FOOD', -3);
define('WATER', -4);
define('UNSEEN', -5);

class Ants
{
    public $turns = 0;
    public $rows = 0;
    public $cols = 0;
    public $loadtime = 0;
    public $turntime = 0;
    public $viewradius2 = 0;
    public $attackradius2 = 0;
    public $spawnradius2 = 0;
    public $map;
    public $myAnts = array();
    public $enemyAnts = array();
    public $myHills = array();
    public $enemyHills = array();
    public $deadAnts = array();
    public $food = array();
    public $mayfood = array();	//	if foods didnt reach ants, mayfoods try
    public $water = array();
    public $max_range = 0;

    public $AIM = array(
        'n' => array(-1, 0),
        'e' => array(0, 1),
        's' => array(1, 0),
        'w' => array(0, -1) );
    public $RIGHT = array (
        'n' => 'e',
        'e' => 's',
        's' => 'w',
        'w' => 'n');
    public $LEFT = array (
        'n' => 'w',
        'e' => 'n',
        's' => 'e',
        'w' => 's');
    public $BEHIND = array (
        'n' => 's',
        's' => 'n',
        'e' => 'w',
        'w' => 'e'
        );


    public function issueOrder($aRow, $aCol, $direction)
    {
        printf("o %s %s %s\n", $aRow, $aCol, $direction);
        flush();
    }

    public function finishTurn()
    {
        echo("go\n");
        flush();
    }
    
    public function setup($data)
    {
        foreach ( $data as $line) {
            if (strlen($line) > 0) {
                $tokens = explode(' ',$line);
                $key = $tokens[0];
                if (property_exists($this, $key)) {
                    $this->{$key} = (int)$tokens[1];
                }
            }
        }
        for ( $row=0; $row < $this->rows; $row++) {
            for ( $col=0; $col < $this->cols; $col++) {
                $this->map[$row][$col] = LAND;
            }
        }
	      /*  
	    $this->viewradius2 = sqrt($this->viewradius2);
	    $this->attackradius2 = sqrt($this->attackradius2);
	    $this->spawnradius2 = sqrt($this->spawnradius2);
        */
        $this->max_range = $this->rows*$this->cols/100;
    }

    /** not tested */


    public function update($data)
    {
        // clear ant and food data
        foreach ( $this->myAnts as $ant ) {
            list($row,$col) = $ant;
            $this->map[$row][$col] = LAND;
        }
        $this->myAnts = array();

        foreach ( $this->enemyAnts as $ant ) {
            list($row,$col) = $ant;
            $this->map[$row][$col] = LAND;
        }
        $this->enemyAnts = array();

        foreach ( $this->deadAnts as $ant ) {
            list($row,$col) = $ant;
            $this->map[$row][$col] = LAND;
        }
        $this->deadAnts = array();

        foreach ( $this->food as $ant ) {
            list($row,$col) = $ant;
			$this->map[$row][$col] = LAND;
        }
        $this->mayfood = array_merge($this->mayfood, $this->food);
        $this->food = array();
//        instead of emptying, put them in maybe foods
        
        // keep an eye on previously seen food
        
        foreach ( $this->myHills as $hill ) {
            list($row,$col) = $hill;
            $this->map[$row][$col] = LAND;
        }
        $this->myHills = array();
//        $this->enemyHills = array();
				
        # update map and create new ant and food lists
        foreach ( $data as $line) {
            if (strlen($line) > 0) {
                $tokens = explode(' ',$line);

                if (count($tokens) >= 3) {
                    $row = (int)$tokens[1];
                    $col = (int)$tokens[2];
                    $loc = array($row,$col);
                    $tloc = tag($loc);
                    if ($tokens[0] == 'a') {
                        $owner = (int)$tokens[3];
                        $this->map[$row][$col] = $owner;
                        if( $owner === 0) {
                            $this->myAnts [$tloc]= $loc;
                            $this->map[$row][$col] = MY_ANT;
                        } else {
                            $this->enemyAnts [$tloc]= $loc;
                            $this->map[$row][$col] = ENEMY_ANT;
                        }
                    } elseif ($tokens[0] == 'f') {
                        $this->map[$row][$col] = FOOD;
                        $this->food [$tloc]= $loc;
                    } elseif ($tokens[0] == 'w') {
                        $this->water [$tloc]= $loc;
                        $this->map[$row][$col] = WATER;
                    } elseif ($tokens[0] == 'd') {
                        if ($this->map[$row][$col] === LAND) {
                            $this->map[$row][$col] = DEAD;
                        }
                        $this->deadAnts [$tloc]= $loc;
                    } elseif ($tokens[0] == 'h') {
                        $owner = (int)$tokens[3];
                        if ($owner === 0) {
                            $this->map[$row][$col] = MY_HILL;
                            $this->myHills [$tloc]= $loc;
                        } else {
                            $this->map[$row][$col] = ENEMY_HILL;
                            $this->enemyHills [$tloc]= $loc;
                        }
                    }
                }	//	if
            }	//	if
        }	//	foreach
        
        $this->mayfood = array_diff_key($this->mayfood, $this->food);
        // no need $this->enemyHills = array_unique($this->enemyHills);
    }


    public function passable($row, $col)
    {
        return $this->map[$row][$col] > WATER;
    }

    public function unoccupied($row, $col) {
        return in_array($this->map[$row][$col], array(LAND, DEAD));
    }

    public function destination($row, $col, $direction)
    {
        list($dRow, $dCol) = $this->AIM[$direction];
        $nRow = ($row + $dRow) % $this->rows;
        $nCol = ($col +$dCol) % $this->cols;
        if ($nRow < 0) $nRow += $this->rows;
        if ($nCol < 0) $nCol += $this->cols;
        return array( $nRow, $nCol );
    }

    public function distance($row1, $col1, $row2, $col2) {
        $dRow = abs($row1 - $row2);
        $dCol = abs($col1 - $col2);

        $dRow = min($dRow, $this->rows - $dRow);
        $dCol = min($dCol, $this->cols - $dCol);
		
		return $dRow + $dCol;
        return sqrt($dRow * $dRow + $dCol * $dCol);
    }

    public function direction($row1, $col1, $row2, $col2) {
        $d = array();
        $row1 = $row1 % $this->rows;
        $row2 = $row2 % $this->rows;
        $col1 = $col1 % $this->cols;
        $col2 = $col2 % $this->cols;

        if ($row1 < $row2) {
            if ($row2 - $row1 >= $this->rows/2) {
                $d []= 'n';
            }
            if ($row2 - $row1 <= $this->rows/2) {
                $d []= 's';
            }
        } elseif ($row2 < $row1) {
            if ($row1 - $row2 >= $this->rows/2) {
                $d []= 's';
            }
            if ($row1 - $row2 <= $this->rows/2) {
                $d []= 'n';
            }
        }
        if ($col1 < $col2) {
            if ($col2 - $col1 >= $this->cols/2) {
                $d []= 'w';
            }
            if ($col2 - $col1 <= $this->cols/2) {
                $d []= 'e';
            }
        } elseif ($col2 < $col1) {
            if ($col1 - $col2 >= $this->cols/2) {
                $d []= 'e';
            }
            if ($col1 - $col2 <= $this->cols/2) {
                $d []= 'w';
            }
        }
        return $d;

    }


    public static function run($bot)
    {
        $ants = new Ants();
        $map_data = array();
        while(true) {
            $current_line = fgets(STDIN,1024);
            $current_line = trim($current_line);
            if ($current_line === 'ready') {
                $ants->setup($map_data);
                $ants->finishTurn();
                $map_data = array();
            } elseif ($current_line === 'go') {
                $ants->update($map_data);
                $bot->doTurn($ants);
                $ants->finishTurn();
                $map_data = array();
            } else {
                $map_data []= $current_line;
            }
        }

    }
    }
