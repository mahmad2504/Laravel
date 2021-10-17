<?php

namespace App\Http\Controllers\Confluence;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Apps\Confluence\Confluence;
use Redirect,Response, Artisan;
use Carbon\Carbon;
use App\Libs\Utility\Calendar;
class ConfluenceController extends Controller
{
	public function Decode($str)
	{
		if (strpos(strtolower($str), 'feature freeze') !== false) 
		{
			if ((strpos(strtolower($str), 'qa') === false)&&(strpos(strtolower($str), 'pre') === false))
				return 'FF';
			return '';
		}
		else if (strpos(strtolower($str), 'documentation review') !== false) 
		{
			return 'DR';
		}
		else if (strpos(strtolower($str), 'doc review') !== false) 
		{
			return 'DR';
		}
		
		else if (strpos(strtolower($str), 'documentation comple') !== false) 
		{
			return 'DC';
		}
		else if (strpos(strtolower($str), 'code freeze') !== false) 
		{
			if ((strpos(strtolower($str), 'qa') === false)&&(strpos(strtolower($str), 'pre') === false))
				return 'CF';
			return '';
		}
		else if (strpos(strtolower($str), 'release') !== false) 
		{
			if (strpos(strtolower($str), 'preview') !== false)
				return 'PR';
			else
				return 'RL';
			return '';
		}
		else if (strtolower(trim($str))=='ff')
		{
			return 'FF';
		}
		else if (strtolower(trim($str))=='cf')
		{
			return 'CF';
		}
		else if (strtolower(trim($str))=='tc')
		{
			return 'TC';
		}
		else if (strtolower(trim($str))=='qac')
		{
			return 'QAC';
		}
		else if (strtolower(trim($str))=='dc')
		{
			return 'DC';
		}
		else
		{
			return '';
		}
	}
	public function GetMilestones($rec,$pcr_inprogress=0,$debug)
	{
		$values = [];
		
		//$t=count($rec->milestones);
		$m = [];
	
		foreach($rec->milestones as $milestone)
		{
			$long_name = explode("(",$milestone->name)[0];
			$code = $this->Decode($long_name);
			if($debug==1)
			{
				if($code == '')
					dump($long_name);
			}
			
			//dump($long_name."  ".$code);
	
			$v = new \StdClass();
			if($milestone->duedate > 0)
			{
				$now =  new Carbon('now');
				$ts = $now->getTimeStamp();
				
				$dt = CDateTime($milestone->duedate);
				$year=$dt->format('Y');
				$week=$dt->isoWeek();
		
				$v->duedate = $year."_".$week;
				$v->code=$code;
				$v->completed = $milestone->completed;
				$dt = CDateTime($milestone->duedate);
				$title = $long_name." - ".$dt->format('d M, Y');
				$multiple=0;
				if(isset($m[$v->duedate]))
				{
					$title = $m[$v->duedate]['title']."\r\n".$title;
					if($code  == '')
						$code=$m[$v->duedate]['code'];
					$multiple=1;
				}
				$m[$v->duedate]['title']=$title;
				$m[$v->duedate]['code']=$code;
				if($multiple==1)
					$code=$code."+";
				if($v->completed)
				{
					$v->class = 'green';
					$v->label = '<small title="'.$title.'">'.$code.'</small>';
				}
				else
				{
					if($ts > $milestone->duedate)
					{
						$v->class = 'red';
						$v->label = '<small  title="'.$title.'">'.$code.'</small>';
					}
					else
					{
						if($pcr_inprogress)
						{
							$v->class = 'orange';
							$v->label = '<small title="'.$title.'">'.$code.'</small>';
						}
						else
						{
							$v->class = 'blue';
							$v->label = '<small title="'.$title.'">'.$code.'</small>';
						}
					}
				}
				if($code == '')
				{
					$v->class = 'lightgrey';
					$v->label = '<small title="'.$title.'">.</small>';
				}
				$values[] = $v;
			}
			/*else
			{
				$dt =  new Carbon('now');
				$year=$dt->format('Y');
				$week=$dt->isoWeek();
				
				$v = new \StdClass();
				$v->duedate = $year."_".$week;
				$v->label = '<small title=""> >> </small>';
				$values[] = $v;
			}*/
		}
		return $values;
	}
	public function Index(Request $request)
	{
		$start = Carbon::now();
		$start->subDays(63);
		$end = Carbon::now();
		$end=  $end->addDays(365);
		$calendar =  new Calendar($start,$end);
		$tabledata = $calendar->GetGridData();
		
		$app = new Confluence();
		$projects = $app->FetchReleasePlans(1);
		$plans = [];
		foreach($projects as $project)
		foreach($project->releaseplans as $releaseplan)
		{
			$plans[] = $releaseplan;
		}
		
		
		usort($plans,function ($a, $b) { return (strtolower($a->category) <=> strtolower($b->category)); });
		foreach($plans as $plan)
		{
			//dump("*****************".$project->id."   ".$plan->name."*****************");
			$url = $plan->url;
			$current = new \StdClass();
			
			$pcr_inprogress=0;
			foreach($plan->pcrs as $pcr)
			{
				if($pcr->jira->statuscategory != 'resolved')
					$pcr_inprogress=1;
			}
		
			//dump($plan->baseline);
			$current->values = $this->GetMilestones($plan->baseline,$pcr_inprogress,$request->debug);
			$baseline_complete=0;
			foreach($current->values as $value)
			{
				if($value->code == 'RL')
					$baseline_complete=1;
			}
			if($baseline_complete==0)
				$current->values = $this->GetMilestones($plan->baseline,1,$request->debug);
			//dump($current->values);
			$current->desc = "Baseline";
			
			foreach($plan->pcrs as $pcr)
			{
				if($pcr->jira->statuscategory == 'resolved')
				{
					$current->values = $this->GetMilestones($pcr,$pcr_inprogress,$request->debug);
					$jurl = explode('rest',$pcr->jira->self)[0]."browse/".$pcr->jira->key;
					$current->desc = '<a title="'.$pcr->jira->status.'" class="jiralink" href="'.$jurl.'">'.$pcr->jiraid.'</a>';
					$current_pcr = $pcr->jiraid;
				}
			}
			$current->category = $plan->category;
			$current->name = '<a href="'.$url.'">'.$plan->name.'</a>';
			$data[] = $current;
			
			$lastpcr = null;
			foreach($plan->pcrs as $pcr)
			{
				$lastpcr = $pcr;
				
			}
			if($lastpcr != null)
			{
				if($lastpcr->jira->statuscategory != 'resolved')
				{
					$pcr = new \StdClass();
					$pcr->values = $this->GetMilestones($lastpcr,1,$request->debug);
					$jurl = explode('rest',$lastpcr->jira->self)[0]."browse/".$lastpcr->jira->key;
					$pcr->desc = '<a title="'.$lastpcr->jira->status.'" class="jiralink" href="'.$jurl.'">'.$lastpcr->jiraid.'</a>';
					$data[] = $pcr;
				}
			}
		}
		
		return view('confluence.index',compact('data','tabledata'));
	}
}
