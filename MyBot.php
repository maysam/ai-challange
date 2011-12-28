<?php 
require_once 'Ants.php';

class MyBot
{
	public static $debug = false;
//	protected $track, $diner;
	protected $process_time, $move_time, $total_time, $start_time, $compare_time;
	protected $enemy_hill_time, $enemy_ant_time, $food_time, $maybe_food_time, $my_hill_time;
	protected $turn;
	protected $move = null;
	protected $from = null;
	protected $value = null;
	protected $_move = null;
	protected $_from = null;
	protected $_value = null;
	protected $map = null;
  protected $directions = array('n','e','s','w');
	protected $slow = false;
	protected $priorities = array(
		'a' => array('n' => 0,'e' => 0,'w' => 0,'s' => 0),
		'n' => array('n' => 10,'e' => 20,'w' => 20,'s' => 30),
		'e' => array('e' => 10,'n' => 20,'s' => 20,'w' => 30),
		'w' => array('w' => 10,'n' => 20,'s' => 20,'e' => 30),
		's' => array('s' => 10,'e' => 20,'w' => 20,'n' => 30),	
	);
	
	protected $dirs = null;
	protected $dams = null;
	protected $lastmove = null;
  
	protected $mapvalue = null;
	
	private function makeMove(&$ants, $ant, $tant, $dir, $dest, $tdest, $ant_ratio)
	{
		$changed = false;
		list($aRow, $aCol) = $ant;
		list($dRow, $dCol) = $dest;
		
		$ignore = false;
		if($ant_ratio>50)
		{
			foreach($ants->myHills as $thill => $myhill)
			{
			  if($tant==$thill)
			  {
			  	$ignore = true;
			  	break;
			  }
			}
		}
		if(!$ignore)
		{
			$ants->issueOrder($aRow, $aCol, $dir);
			//	$this->track[$tdest] = $tant;
		}
		$this->lastmove[$tdest] = $dir;
		unset($ants->enemyHills[$tdest]);
		unset($ants->food[$tdest]);
		unset($ants->mayfood[$tdest]);
		foreach($this->dirs[$dRow][$dCol] as $_dest)
		{
			$t_dest = tag($_dest);
			if(isset($ants->food[$t_dest]))
			{
/*
				$temp = $tdest;
				$count = 100;
				while(isset($this->track[$temp]) and ($count!=0))
				{
					$count --;
//					if($count<90)
					if(isset($this->diner[$temp]))
						$this->diner[$temp] ++;
					else
						$this->diner[$temp] = 2;
					$temp = $this->track[$temp];
				}
				*/
				unset($ants->food[$t_dest]);
			}
			unset($ants->mayfood[$t_dest]);
			//		add values here ...
		}
		//	no more moves for this ant
		
		foreach($this->move[$tant] as $_dest => $p)
		{	//	all moves by this ant is removed, there should be no duplicate moves!!!
			$v = $this->mapvalue[$_dest];
			unset($this->value[$v][$p.' '.move($tant,$_dest)]);
			if(count($this->value[$v])==0)
				unset($this->value[$v]);
			unset($this->from[$_dest][$tant]);
			if(count($this->from[$_dest])==0)
			{
				unset($this->from[$_dest]);
				unset($this->mapvalue[$_dest]);
			}
		}
		unset($this->move[$tant]);

		if(isset($this->_move[$tant]))
		{
			foreach($this->_move[$tant] as $_dest => $p)
			{	//	all moves by this ant is removed, there should be no duplicate moves!!!
				$v = $this->mapvalue[$_dest];
				unset($this->_value[$v][$p.' '.move($tant,$_dest)]);
				if(count($this->_value[$v])==0)
					unset($this->_value[$v]);
				unset($this->_from[$_dest][$tant]);
				if(count($this->_from[$_dest])==0)
				{
					unset($this->_from[$_dest]);
					unset($this->mapvalue[$_dest]);
				}
			}
			unset($this->_move[$tant]);
		}
		
		if(isset($this->from[$tdest]))
		{
			foreach($this->from[$tdest] as $_ant => $p)
			{	//	all moves to this loc should be removed, we are not killing eachother
				$v = $this->mapvalue[$tdest];
				unset($this->value[$v][$p.' '.move($_ant,$tdest)]);
				if(count($this->value[$v])==0)
					unset($this->value[$v]);
				unset($this->move[$_ant][$tdest]);
			}
			unset($this->from[$tdest]);
			unset($this->mapvalue[$tdest]);
		}
			
		if(isset($this->_from[$tant]))
		{		//	those moving to its place, activate them
			foreach($this->_from[$tant] as $_ant => $p)
			{	//	all moves to this loc should be removed, we are not killing eachother
				$v = $this->mapvalue[$tant];
				$move = $p.' '.move($_ant,$tant);
				if(!$ignore)
					$this->value[$v][$move] = $this->_value[$v][$move];
				unset($this->_value[$v][$move]);
				if(count($this->_value[$v])==0)
					unset($this->_value[$v]);
				if(!isset($this->_move[$_ant][$tant]))
				 throw(new Exception('$this->_from['.$tant.']['.$_ant.'] ('.$this->_from[$tant][$_ant].')= $this->_move['.$_ant.']['.$tant.'] ('.$this->_move[$_ant][$tant].');'));
				if(!$ignore)
					$this->move[$_ant][$tant] = $this->_move[$_ant][$tant];
				unset($this->_move[$_ant][$tant]);
				$changed = true;
			}
			if(!$ignore)
				$this->from[$tant] = $this->_from[$tant];
			unset($this->_from[$tant]);
			if($ignore)
				unset($this->mapvalue[$tant]);
		}
		return $changed;
  }
	
  public function doTurn( &$ants )
  {
  	$this->start_time = microtime(true);
  	$this->compare_time = $this->start_time + $ants->turntime/1200;
   	$this->move = array();
  	$this->from = array();
  	$this->value = array();
  	$this->_move = array();
  	$this->_from = array();
  	$this->_value = array();
		$this->mapvalue = null;
		if(!isset($this->dirs))
		{
	   		for($r=0; $r<$ants->rows; $r++)
	 				for($c=0; $c<$ants->cols; $c++)
	 					$this->dirs[$r][$c] = array(
						'n'=>array(($r-1+$ants->rows) % $ants->rows,$c),
						'w'=>array($r,								($c-1+$ants->cols) % $ants->cols),
						's'=>array(($r+1) % $ants->rows,			$c),
						'e'=>array($r,								($c+1) % $ants->cols)
					);
		}

	$this->turn ++;
	$this->water_processMap($ants);


	$this->slow = $this->processMap($ants);


	$ant_count = count($ants->myAnts);
	$hill_count = count($ants->myHills);
	if($hill_count>0)
		$ant_ratio = $ant_count / $hill_count;
	else
		$ant_ratio = 1024;
	$rand = 1;
  foreach ($ants->myAnts as $tant => $ant )
  {
  	list ($aRow, $aCol) = $ant;
      if(isset($this->lastmove[$tant]))
      {
        $lastmove = $this->lastmove[$tant];
      }
      else
      {
      	$lastmove = 'a';
      }
      $directions = $this->dirs[$aRow][$aCol];
      foreach ($directions as $dir => $dest) {
          list($dRow, $dCol) = $dest;
          
          $tdest = tag($dest);
          if(isset($ants->food[$tdest]))
          {
          	unset($ants->food[$tdest]);
          	unset($ants->mayfood[$tdest]);
          	continue;
          }
          $dcount = $this->dams[$dRow][$dCol];
          /*
          if(isset($this->diner[$tdest]))
          	$temp = $this->diner[$tdest];
          else
          	$temp = 1;
          	*/
          $value= $dcount*1.9 + ($this->map[$dRow][$dCol] );
          $rand++;
          if($rand > 7) $rand = 1;
          $this->possible_move($ants, $ant, $tant, $dest, $tdest, $dir, $value, $this->priorities[$lastmove][$dir]+	$rand );
      }
  }

	//	don't move ant from hill if we have enough ants around
	if(false)	//	remove this block
	if($ant_ratio>50)
	{
		foreach($ants->myHills as $tant => $myhill)
		{
//			list($hRow, $hCol) = $myhill;
		  if(isset($this->move[$tant]))
		  {
  			error_log('just spawned');
		  	//	abort move
				foreach($this->move[$tant] as $_dest => $p)
				{	//	all moves by this ant is removed, there should be no duplicate moves!!!
					$v = $this->mapvalue[$_dest];
					unset($this->value[$v][$p.' '.move($tant,$_dest)]);
					if(count($this->value[$v])==0)
						unset($this->value[$v]);
					unset($this->from[$_dest][$tant]);
					if(count($this->from[$_dest])==0)
					{
						unset($this->from[$_dest]);
						unset($this->mapvalue[$_dest]);
					}
				}
				unset($this->move[$tant]);
				if(isset($this->_move[$tant]))
				{
					foreach($this->_move[$tant] as $_dest => $p)
					{	//	all moves by this ant is removed, there should be no duplicate moves!!!
						$v = $this->mapvalue[$_dest];
						unset($this->_value[$v][$p.' '.move($tant,$_dest)]);
						if(count($this->_value[$v])==0)
							unset($this->_value[$v]);
						unset($this->_from[$_dest][$tant]);
						if(count($this->_from[$_dest])==0)
						{
							unset($this->_from[$_dest]);
							unset($this->mapvalue[$_dest]);
						}
					}
					unset($this->_move[$tant]);
				}

				if(isset($this->_from[$tant]))
				{		//	those moving to its place, activate them
					foreach($this->_from[$tant] as $_ant => $p)
					{	//	all moves to this loc should be removed, we are not killing eachother
						$v = $this->mapvalue[$tant];
						$move = $p.' '.move($_ant,$tant);
///						$this->value[$v][$move] = $this->_value[$v][$move];
						unset($this->_value[$v][$move]);
						if(count($this->_value[$v])==0)
							unset($this->_value[$v]);
						if(!isset($this->_move[$_ant][$tant]))
						 throw(new Exception('$this->_from['.$tant.']['.$_ant.'] ('.$this->_from[$tant][$_ant].')= $this->_move['.$_ant.']['.$tant.'] ('.$this->_move[$_ant][$tant].');'));
		//				$this->move[$_ant][$tant] = $this->_move[$_ant][$tant];
						unset($this->_move[$_ant][$tant]);
						$changed = true;
					}
			//		$this->from[$tant] = $this->_from[$tant];
					unset($this->_from[$tant]);
				}
			}
		}
	}
  	
	
  if(!isset($this->mapvalue))
  {
  	error_log('where are the moves');
  	return;
  }
  	asort($this->mapvalue, SORT_NUMERIC);
    $ant_moved = 0;
	//  	error_log($this->turn." with ".count($this->mapvalue)	);
    reset($this->mapvalue);  
    $movecount = 0;
	  while($ant_moved < $ant_count and $movecount<100)
	  {
	  	$movecount++;
//	  	error_log("($ant_moved < $ant_count)");
	  	$cv = current($this->mapvalue);
	  	$key = key($this->mapvalue);
	  	if($key === null)
	  	{
	  		error_log('breaking out with '.count($this->mapvalue));	
	  		if(count($this->mapvalue)==0)
		  		break;
		  	else
		  	{
			  	reset($this->mapvalue);
		  		continue;
		  	}
	  	}
    	if(isset($this->value[$cv]))
    	{
      	$otherdests = $this->value[$cv];
      	if(count($otherdests)>1)
        	ksort($otherdests, SORT_NUMERIC);
     		if(list($v1, $otherdest) = each($otherdests))
     		{
	   			list($ant, $tant, $dir, $dest, $tdest) = $otherdest;
	   			$this->makeMove($ants, $ant, $tant, $dir, $dest, $tdest, $ant_ratio);
	   			$ant_moved ++;
	   			reset($this->mapvalue);
	   		} else {
	   			error_log("kheili khari");
	   		}
 			} else {
 				if(isset($this->_value[$cv]))
 				{
			  	next($this->mapvalue);
 				}
 				else
 				{
 					error_log('akhe chera');
	   			unset($this->mapvalue[$key]);
			  	next($this->mapvalue);
			  }
 			}
    }
/*
		$this->move_time = microtime(true);



		$this->move_time -= $this->process_time;
		$this->process_time -= $this->water_time;

		$this->my_hill_time -= $this->maybe_food_time;
		$this->maybe_food_time -= $this->food_time;
		$this->food_time -= $this->enemy_ant_time;
		$this->enemy_ant_time -= $this->enemy_hill_time;
		$this->enemy_hill_time -= $this->water_time;

		$this->water_time -= $this->start_time;		
		$this->total_time = microtime(true)-$this->start_time;
	
		$this->water_time = round($this->water_time /$this->total_time,4)*100;
		$this->process_time = round($this->process_time/$this->total_time,4)*100;

		$this->enemy_hill_time = round($this->enemy_hill_time/$this->total_time,4)*100;
		$this->enemy_ant_time = round($this->enemy_ant_time/$this->total_time,4)*100;
		$this->food_time = round($this->food_time/$this->total_time,4)*100;
		$this->maybe_food_time = round($this->maybe_food_time/$this->total_time,4)*100;
		$this->my_hill_time = round($this->my_hill_time/$this->total_time,4)*100;

		$this->move_time = round($this->move_time/$this->total_time,4)*100;
		
  	$this->total_time =  round($this->total_time*1000/$ants->turntime,4)*100;


		$this->water_time = round($this->water_time *$this->total_time)/100;
		$this->process_time = round($this->process_time*$this->total_time)/100;

		$this->enemy_hill_time = round($this->enemy_hill_time*$this->total_time)/100;
		$this->enemy_ant_time = round($this->enemy_ant_time*$this->total_time)/100;
		$this->food_time = round($this->food_time*$this->total_time)/100;
		$this->maybe_food_time = round($this->maybe_food_time*$this->total_time)/100;
		$this->my_hill_time = round($this->my_hill_time*$this->total_time)/100;

		$this->move_time = round($this->move_time*$this->total_time)/100;

		if($this->slow)
		{
	    error_log("slowing with $ant_count ants $ant_moved moved");
			error_log("{$this->turn}: W={$this->water_time}% P={$this->process_time}% { EH={$this->enemy_hill_time}% EA={$this->enemy_ant_time}% F={$this->food_time}% MF={$this->maybe_food_time}% MH={$this->my_hill_time}% } M={$this->move_time}% using {$this->total_time}%");
		}
	*/
  }
 
 	public function possible_move($ants, $ant, $tant, $dest, $tdest, $dir, $value, $priority)
 	{ 	
 		$move = move($tant, $tdest);
 		if(isset($this->mapvalue[$tdest]))
 			assert($this->mapvalue[$tdest] == $value);
 		$this->mapvalue[$tdest] = $value;
 		if($ants->map[$dest[0]][$dest[1]]==MY_ANT)    //   in_array($dest,$ants->myAnts)
 		{
     		$this->_move[$tant][$tdest] = $priority;
     		$this->_from[$tdest][$tant] = $priority;
       	$this->_value[$value][$priority.' '.$move] = array($ant, $tant, $dir, $dest, $tdest);
 		}
 		else
 		{
     		$this->move[$tant][$tdest] = $priority;
     		$this->from[$tdest][$tant] = $priority;
       	$this->value[$value][$priority.' '.$move] = array($ant, $tant, $dir, $dest, $tdest);
 		}
 	}

	private function _process($ants, $src, &$antmet, &$notmet)
	{
		$scount = count($src);
		$_demote = array();
		$ant_count = count($ants->myAnts);
    while(($scount>0) && ($notmet>0))
	  {
//	  	error_log("scount = $scount");
	  	if(microtime(true)>$this->compare_time)
	  		return true;
			$_src = array();
	    $demote = array();
	    $i = 0;
   		foreach($src as $tloc => $element)
   		{
   			$i++;
   			if($i>100)
   				break;
   			extract($element);
   			
	      if(isset($_demote[$origin]))
	      	continue;
        $seen[$origin][$tloc] = true;
   			list($aRow, $aCol) = $target;
 				if ($ants->map[$aRow][$aCol]>WATER)
  			{
   				if( (($this->map[$aRow][$aCol]>$point) && ($step>0)) ||  ($this->map[$aRow][$aCol]==1024))
   				{
   					$this->map[$aRow][$aCol] = $point;
		        if(($point+$step - $until)*($point - $until)>0)
	    			foreach($this->dirs[$aRow][$aCol] as $dir => $dest)
		    		{

            	list($drow,$dcol) = $dest;
              $tdest =tag($dest);
              if(isset($seen[$origin][$tdest]))
              	continue;
              	
    //          if(0) //	what is this	
    //	if you are looking for an ant, do not go further than the hil
              if(($ants->map[$drow][$dcol] == MY_HILL))
			        {
                if(!isset($antmet[$tdest]))
                {
					        if($first_ant==1)
  	              	$demote[$origin] = true;
//                	$antmet[$tdest] = true;
  //                $notmet --;
                }
			        }
			        
              if(($ants->map[$drow][$dcol] == MY_ANT))
			        {
                if(!isset($antmet[$tdest]))
                {
					        if($first_ant==1)
					        {
  	              	$demote[$origin] = true;
  	              }
                	$antmet[$tdest] = true;
                  $notmet --;
                 // error_log("{$this->turn}: notmet=$notmet");
                }
			        }			        
              if(!isset($_src[$tdest]) || (($_src[$tdest]['point']> ($point+$step)) && ($step>0)))
              {
              	$_src[$tdest] = array('point' => $point+$step, 'step' => $step, 'until' => $until, 'first_ant' => $first_ant, 'target' => $dest, 'origin' => $origin);	
              }
		    		}
   				}	   					
   			}
   		}
   		
   		$_demote = $demote;
      $src = $_src;
      $scount = count($_src);
   		//	exit if array is empty   			
   	}
   	return false;
 	}	// function

	private function water_processMap($ants)
	{
		//	TODO:	keep the ants as far apart as possible
		//	TODO:	 ants go in the direction of least scent
		//	also add for water so bots go through line
		if(!isset($this->dams))
	    $this->dams = array_fill(0, $ants->rows, array_fill(0, $ants->cols, 0));
		$dam3 = null;
		$dam2 = null;
    
    foreach($ants->water as $twater=>$waterlocation)
    {
    	list($aRow, $aCol) = $waterlocation;
   		if(isset($this->dirs[$aRow][$aCol])) // && $ants->map[$aRow][$aCol]>WATER) 
   		{
	    	$dirs = $this->dirs[$aRow][$aCol];
   			unset($this->dirs[$aRow][$aCol]);
   			unset($this->dams[$aRow][$aCol]);
   			unset($dam2[$twater]);
   			unset($dam3[$twater]);
   			foreach($dirs as $dir => $neighbour)
   			{
   				list($nRow, $nCol) = $neighbour;
   				unset($this->dirs[$nRow][$nCol][$ants->BEHIND[$dir]]);
   				if(isset($this->dams[$nRow][$nCol]))
   				{
   					$this->dams[$nRow][$nCol]++;
   					if($this->dams[$nRow][$nCol]==2)
   					{
   						$dam2[tag($neighbour)] = $neighbour;
   					}
   					elseif($this->dams[$nRow][$nCol]==3)	// 3 and we don't care for 3+
   					{
   						unset($dam2[tag($neighbour)]);
   						reset($this->dirs[$nRow][$nCol]);
   						if(list($_dir, $_neigh) = each($this->dirs[$nRow][$nCol]))
   						{
   								// get its only member
   							$dam3[tag($neighbour)] = array($neighbour, $_neigh);
   						}
   					}
   				}
   				else
   				{
 						$this->dams[$nRow][$nCol] = 1;
 					}
   			}
   		}
   	}
		while(isset($dam3))
   	{
	  	if(microtime(true)>$this->compare_time)	return false;
   		unset($_dam3);
   		foreach($dam3 as $t => $cells)
   		{
   			list($cell, $neighbour) = $cells;
 				list($row, $col) = $cell;
   				//	list($dir, $neighbour) = each($this->dirs[$row][$col]);
   			list($nrow, $ncol) = $neighbour;
   			if(isset($this->dams[$nrow][$ncol]))
  	 			$this->dams[$nrow][$ncol]++;
   			else
	   			$this->dams[$nrow][$ncol] = 1;
 				if($this->dams[$nrow][$ncol]==2)
	   			$dam2[$t] = $neighbour;
   			if($this->dams[$nrow][$ncol]==3)
   			{
   				foreach($this->dirs[$nrow][$ncol] as $neigh)
   				{
   					list($nRow, $nCol) = $neigh;
   					if($this->dams[$nRow][$nCol] != 3)
   					{
	   					$_dam3[$t] = array($neighbour, $neigh);
   					}
   				}
   			}
   		}
   		if(!isset($_dam3))
 				break;
 			$dam3 = $_dam3;
 		}		
 		if(isset($dam2))
 			foreach($dam2 as $t => $cell)
 				{
 					list($row, $col) = $cell;
	 				foreach($this->dirs[$row][$col] as $dir=>$neighbour)
	 				{
	 					list($nrow, $ncol) = $neighbour;
	   				$this->dams[$nrow][$ncol] += 0.5;
	 				}
 				}
 	}
 	
 	private function processMap($ants)
	{
		//error_log(count($ants->food).' food and '.count($ants->mayfood).' mayfood');
  	// we need better assessments for battles, but for now let's go greedy
		//	$src[] = array(start,step,until, stop at first ant, pos
   	$hill_count = count($ants->myHills);
   	$ant_count = count($ants->myAnts);
    if(!$ant_count)
    	return false;
    if($hill_count>0)
			$ant_ratio = $ant_count/$hill_count;
		else	
			$ant_ratio = $ant_count;
		$this->map = array_fill(0, $ants->rows, array_fill(0, $ants->cols, 1024));
		$seen = null;
		$antmet = array();
		$notmet = $ant_count;
		$HILL_BASE = 5;
    $MAX_POINT= 20;
    if($this->slow) {
	    $MAX_POINT= 10;
	  }
		if($ant_ratio>50)
			$timer = 5;
		else
			$timer = 1;

		foreach($ants->enemyHills as $tloc => $target)
			$src[$tloc] = array('point' => -4, 'step' => +2, 'until' => $MAX_POINT*$timer, 'first_ant' => 0, 'target' => $target,'origin' => $tloc);
		if(isset($src))
			if($this->_process( $ants, $src, $antmet, $notmet))
			{

				return true;
			}

		unset($src);

		foreach($ants->enemyAnts as $tloc => $target)	// not really into following them
		{
			if($hill_count>0)
   		foreach($ants->myHills as $tloc => $myhill)
   		{
   			list($hRow, $hCol) = $myhill;
				list($tRow, $tCol) = $target;
				$d = $ants->distance($tRow, $tCol, $hRow, $hCol);
				if(($d>14)&&($ant_count<40)) // away
				{
					$src[$tloc] = array('point' => 2*$ants->attackradius2, 'step' => -1, 'until' => 0, 'first_ant' => -1, 'target' => $target,'origin' => $tloc);
					continue 2;
				}
			}
			$src[$tloc] = array('point' => -2, 'step' => 1, 'until' => 2*$ants->attackradius2, 'first_ant' => -1, 'target' => $target,'origin' => $tloc);
		}
		if(isset($src))
			if($this->_process( $ants, $src, $antmet, $notmet))
			{
				return true;
			}
		unset($src);

    if(!$this->slow)
		if($notmet)
		{
			$f = 0;
			foreach($ants->food as $tloc => $target)
			{
				$f++;
				$src[$tloc] = array('point' => +1, 'step' => +1, 'until' => ($ant_count<100?$MAX_POINT:$MAX_POINT/2), 'first_ant' => +1, 'target' => $target,'origin' => $tloc);
			}
			if(isset($src))
				if($this->_process( $ants, $src, $antmet, $notmet))
				{
					return true;
				}
			unset($src);
		}

    if(!$this->slow)
		if($notmet)
		{ 
			foreach($ants->mayfood as $tloc => $target)
			{
				$src[$tloc] = array('point' => +1, 'step' => +1, 'until' => $MAX_POINT/2, 'first_ant' => +1, 'target' => $target,'origin' => $tloc);
			}
			if(isset($src))
				if($this->_process( $ants, $src, $antmet, $notmet))
				{

					return true;
				}
			unset($src);
		}

		
    if(!$this->slow)
		if($notmet)
		if($hill_count>0)
		{		
			if($ant_count<10)
		    $MAX_POINT= 10;
		  else
		    $MAX_POINT= 2;	   			
   		$point = $MAX_POINT;
   		foreach($ants->myHills as $tloc => $myhill)
   		{
				$src[$tloc] = array('point' => $point+1024, 'step' => -1, 'until' => 0+1024, 'first_ant' => 0, 'target' => $myhill,'origin' => $tloc);
   		}
			if(isset($src))
				if($this->_process( $ants, $src, $antmet, $notmet))
				{
					return true;
				}
			unset($src);
		}

   	return false;
	}    // process_map
}	//	class

/**
 * Don't run bot when unit-testing
 */
if( !defined('PHPUnit_MAIN_METHOD') ) {
	if(MyBot::$debug)
	{
		error_reporting(-1);
	}
	else
	{
		error_reporting(0);
		ini_set('display_errors',0);
	}
  Ants::run( new MyBot() );
}		