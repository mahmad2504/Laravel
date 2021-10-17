<?php

namespace App\Libs\Utility;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
class Calendar
{
	public $csprint_no=null;
	public $sprint_duration=3;// in weeks
	function __construct($start,$end)
	{
		$base='2019-12-30';	
		if($start->format('Y')=='2020')
			$base='2019-12-30';	
		else if($start->format('Y')=='2021')
			$base='2021-01-11';	
		else if($start->format('Y')=='2022')
			$base='2021-01-03';	
		else if($start->format('Y')=='2022')
			$base='2021-01-16';	
		else
			dd('Please mark base in sprint calender');
			
		$sprint_number = 1;
		$this->sprint_duration = $this->sprint_duration*7;
		$base = Carbon::parse($base)->startOfWeek();
		$start = Carbon::parse($start)->startOfWeek();
		if($start < $base)
			$start = $base;
	
		$diff= $start->diffInDays($base);
		
		$end = Carbon::parse($end)->endOfWeek(); 
		$years=[];
		$months=[];
		$weeks=[];
		$sprints=[];
		$days=[];
		$period = new CarbonPeriod($start, '1 day', $end);
		$i=$diff;
		$last_sprint_year = null;
		foreach ($period as $key => $date) 
		{
			//format('M d Y');
			//echo $key.' '.$date."<br>";
			//dd($date->format('Y'));
			$year=$date->format('Y');
			$month=$date->format('m');
			$day=$date->format('d');
			$week=$date->isoWeek();
			$wyear=$date->isoWeekYear();
			$today=$date->IsToday()?1:0;
			$sprint_number=floor($i/$this->sprint_duration)+1;
			
			
			
			if(!isset($years[$year]))
				$years[$year]=[];
			
			if(!isset($months[$year."_".$month]))
				$months[$year."_".$month]=[];
			
			if(!isset($weeks[$wyear."_".$week]))
				$weeks[$wyear."_".$week]=[];
			
			//if(!isset($weeks[$month]))
			//	$months[$year."_".$month]=[];
			$sprint_year=$year;
			if($last_sprint_year != null)
			{
				if(isset($sprints[$last_sprint_year."_".$sprint_number]))
				{
					if(count($sprints[$last_sprint_year."_".$sprint_number])<=$this->sprint_duration)
						$sprint_year = $last_sprint_year;
				}
			}
			if(!isset($sprints[$sprint_year."_".$sprint_number]))
			{
				// we are starting new sprint_number
				if($last_sprint_year != null)
				{
					if($last_sprint_year != $sprint_year) // and we are new boundary
					{
						$sprint_number = 1;
						$i=0;
					}
				}
			}
			if(!isset($sprints[$sprint_year."_".$sprint_number]))
			{
				$sprints[$sprint_year."_".$sprint_number]=[];
			
			}
			
			$years[$year][] = $today;
			$months[$year."_".$month][] = $today;
			$weeks[$wyear."_".$week][] = $today;
			$obj=new \StdClass();
			$obj->date = $date->format('Y-m-d');
			$obj->today = $today;
			$sprints[$sprint_year."_".$sprint_number][]=$obj;
			
			
			$last_sprint_year = $sprint_year; 
			
			if($today == 1)
				$this->csprint_no = $sprint_year."_".$sprint_number;
			
				//$this->currentsprint=$sprints[$sprint_number];
			
			$days[$date->format('Y-m-d')]=$today;
			
			$i++;
			//$sprint_number=$i/21;
			//if($i%21==0)
			//	$sprint_number++;
		}
		$this->years=$years;
		$this->months=$months;
		$this->weeks=$weeks;
		$this->days=$days;
		
		$this->sprints=$sprints;
	}
	public function GetGridData()
	{
		$data = new \StdClass();
		$data->years=$this->years;
		$data->months=$this->months;
		$data->weeks=$this->weeks;
		$data->days=$this->days;
		$data->sprints=$this->sprints;
		return $data;
	}
}