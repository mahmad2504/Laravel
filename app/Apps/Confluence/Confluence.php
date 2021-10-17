<?php
namespace App\Apps\Confluence;
use App\Apps\App;
use App\Libs\Jira\Fields;
use App\Libs\Jira\Jira;
use Carbon\Carbon;
use App\Email;
use PhpOffice\PhpSpreadsheet\IOFactory;
class Confluence extends App
{
	public $timezone='Asia/Karachi';
	public $scriptname = 'confluence';
	public $options = 0;
	public $datafolder = "/data/confluence";
	public $jira_fields = ['key','status','statuscategory','summary','assignee','self'];
	public $jira_server = 'EPS';
	public $query="";
	public function __construct($options=null)
    {
		$this->namespace = __NAMESPACE__;
		$this->mongo_server = env("MONGO_DB_SERVER", "mongodb://127.0.0.1");
		$this->options = $options;
		date_default_timezone_set($this->timezone);
		parent::__construct($this);
    }
	public function TimeToRun($update_every_xmin=10)
	{
		return parent::TimeToRun($update_every_xmin);
	}
	public function InConsole($yes)
	{
		
	}
	public function Rebuild()
	{
		$this->options['email']=2;
		$this->db->users->drop();
		$this->db->project->drop();
		$this->db->releaseplan->drop();
		$this->db->porapproval->drop();
		//$this->db->actions->drop();
	}
	public function SendMail($html,$to,$cc,$subject,$attach=true)
	{
		$email = new Email();
		if($attach)
		{
			$email->AddAttachement('public/images/checkmark.png');
			$email->AddAttachement('public/images/incomplete.jpg');
		}
		$email->Send($this->options['email'],$subject,$html,$to,$cc);			
	}
	public function HttpFetch($url,$user=null,$password=null)
	{
		//dump($url);
		$server = 'EPS';
		$token = env("CONFLUENCE_".$server."_TOKEN");
		$ch = curl_init();
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($ch, CURLOPT_URL, $url);
		if($user!=null)
		{
			curl_setopt($ch, CURLOPT_USERPWD, "$user:$password");
		}
		else
			$headers[]= 'Authorization: Bearer '.$token;
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		//dump($headers);
		$result = curl_exec($ch);
		$result = json_decode($result);	
		return $result;
	}
	public function FetchPage($page)
	{
		$url = "https://confluence.alm.mentorg.com/rest/api/content/".$page->id."?expand=body.storage";
		$d = $this->HttpFetch($url);
		return $d->body->storage->value;
	}
	public function FindPages($label)
	{
		$url = 'https://confluence.alm.mentorg.com/rest/api/content/search?cql=label='.$label;
		$url = str_replace(' ', '%20', $url);
		$pages = $this->HttpFetch($url);
		$data = [];
		foreach($pages->results as $page)
		{
			
			$obj = new \StdClass();
			$obj->url = 'https://confluence.alm.mentorg.com/'.$page->_links->webui;
			$obj->id = $page->id;
			$obj->title = $page->title;
			$data[] = $obj;
		}
		return $data;
		//id _links->url
		//dd($pages->results[_links]);
	}
	
	public function FetchUser($userkey)
	{
		$user = $this->Read($userkey,'users');
		if($user == null)
		{
			$url = "https://confluence.alm.mentorg.com/rest/api/user?key=".$userkey;
			$user = $this->HttpFetch($url);
			$url = "https://jira.alm.mentorg.com/rest/api/latest/user?username=".$user->username;
			$server = $this->jira_server;
			$user = env("JIRA_".$server."_USERNAME");
			$password = env("JIRA_".$server."_PASSWORD");
			$user = $this->HttpFetch($url,$user,$password);
			$user->id = $userkey;
			$this->Save($user,'users');	
		}
		return $user;
	}
	public function ReadDate($item)
	{
		$time = $item->getElementsByTagName('time');
		if(isset($time[0]))
		{
			foreach ($time[0]->attributes as $attr) 
			{
				$name = $attr->nodeName;
				$value = $attr->nodeValue;
				return $value;
			}
		}
		return null;
	}
	public function ReadJiraTicketId($item)
	{
		$obj = null;
		$acparameters = $item->getElementsByTagName('acparameter');
		$lastindex = count($acparameters)-1;
		if($lastindex < 0)
			return null;
		return $acparameters[$lastindex]->nodeValue;
	}
	public function ReadUsers($item)
	{
		$output = [];
		$users = $item->getElementsByTagName('riuser');
		foreach ($users as $user) 
		{
			foreach ($user->attributes as $attr) 
			{
				$name = $attr->nodeName;
				$value = $attr->nodeValue;
				$output[] = $value;
			}
		}
		return $output;
	}
	public function ReadTasks($item)
	{
		if($item == null)
			return [];
		
		$tasks = [];
		$actasks = $item->getElementsByTagName('actask');
		for($k=0;$k<count($actasks);$k++)
		{
			$task = new \StdClass();
			$actask = $actasks[$k];
			$id = $actask->getElementsByTagName('actask-id');
			$task->taskid = $id[0]->nodeValue;
			$status = $actask->getElementsByTagName('actask-status');
			$task->status = $status[0]->nodeValue;
			$task_body = $actask->getElementsByTagName('actask-body');
			$task->text = $task_body[0]->nodeValue;
			$riuser = $task_body[0]->getElementsByTagName('riuser');
			if(isset($riuser[0]))
			foreach ($riuser[0]->attributes as $attr) 
			{
				$name = $attr->nodeName;
				$value = $attr->nodeValue;
				$task->$name = $value;
			}
			$time = $task_body[0]->getElementsByTagName('time');
			if(isset($time[0]))
			foreach ($time[0]->attributes as $attr) 
			{
				$name = $attr->nodeName;
				$value = $attr->nodeValue;
				$task->$name = $value;
			}
			$tasks[]=$task;
		}	
		return $tasks;
	}
	public function CleanXML($table)
	{
		$table = '<table '.$table;
		$subtables = explode('</table>',$table);
		$table = $subtables[0].'</table>';
					
		$table = str_replace('ac:','ac',$table);
		$table = str_replace('ri:','ri',$table);
		$table = str_replace('&nbsp','',$table);
		return $table;
	}
	public function Identify($table)
	{
		$xmlDoc = new \DOMDocument();
		@$xmlDoc->loadXML($table);
		$rows = $this->GetTblRows($xmlDoc);
		if(count($rows)==0)
			return 'unknown';
		$cells = $this->GetCells($rows[0]);
		$code = strtolower(trim($cells[0]->nodeValue));
		$code = str_replace(';','',$code );
		if(( $code=='por sign off')||
			( $code=='por sign-off'))
			return 'POR SIGN OFF';
		else if($code=='por')
			return 'RELEASE PLAN';
		else
			return 'UNKNOWN';
	}
	public function GetCells($row)
	{
		$data = [];
		$headers = $row->getElementsByTagName('th');
		foreach($headers as $h)
			$data[] = $h;
		
		$headers = $row->getElementsByTagName('td');		
		foreach($headers as $h)
			$data[] = $h;
			
		return $data;
	}
	public function GetTblRows($item)
	{
		return $item->getElementsByTagName('tr');
	}
	public function FetchTables($xml)
	{
		$tables = explode('<table',$xml);	
		$data = [];
		foreach($tables as $table)
		{
			if(trim($table) != "")
			{
				$table = $this->CleanXML($table);
				$data[] = $table;
			}
		}
		return $data;
	}
	public function GetReleasePlans($table)
	{
		$xmlDoc = new \DOMDocument();
		@$xmlDoc->loadXML($table);
		$rows = $this->GetTblRows($xmlDoc);
		if(count($rows)==0)
			return null;
		
		if(count($rows)<4)
			return null;
		
		$cells = $this->GetCells($rows[0]);
		if(count($cells)<4)
			return null;
		
		$category = $cells[1]->nodeValue;
		$category = str_replace(";","",$category);
		$name = $cells[2]->nodeValue;
		$name = str_replace(";","",$name);
		$pm = $this->ReadUsers($cells[3]);
		
		$cells = $this->GetCells($rows[1]);
		
		
		$pcrs=[];
		foreach($cells as $cell)
		{
			$jiraid = $this->ReadJiraTicketId($cell);
			if($jiraid != null)
			{
				$pcrs[$jiraid] = new \StdClass();
				$pcrs[$jiraid]->project = $name;
				$pcrs[$jiraid]->jiraid = $jiraid;
				$pcrs[$jiraid]->milestones=[];
			}
		}
		
		$cells = $this->GetCells($rows[2]);
		$c1 = count($cells)-count($pcrs);
		
		$col=0;
		$milestone = $cells[$col++]->nodeValue;
		$milestone = str_replace(";","",$milestone);
		//if($project=='Nucleus 4.1.0 (RISC-V)')
		//{
		//	dump(count($cells));
		//	dump($milestone);
		//}
		$duedate =	CTimestamp($this->ReadDate($cells[$col++]));
		$baseline =  new \StdClass();
		$baseline->project=$name;
		$baseline->milestones=[];
		$bl = new \StdClass();
		$bl->name=$milestone;
		$bl->duedate=$duedate;
		
		$last_cell=$cells[count($cells)-1];
		$completed=0;
		$completedon = CTimestamp($this->ReadDate($last_cell));
		if($completedon == 0)
		{
			if( (strtolower($last_cell->nodeValue) == 'complete')||
				(strtolower($last_cell->nodeValue) == 'completed'))
				$completed=1;
		}
		else
			$completed=1;
		$bl->completed=$completed;
		$bl->completedon=$completedon;
		$baseline->milestones[] = $bl;
		foreach($pcrs as $pcr)
		{
			//dump($col);
			$duedate =	CTimestamp($this->ReadDate($cells[$col++]));
			$bl = new \StdClass();
			$bl->name=$milestone;
			
			$bl->duedate=$duedate;
			$bl->completed=$completed;
			$bl->completedon=$completedon;	
			$pcr->milestones[] = $bl;
			$pcr->tasks = $this->ReadTasks($cells[$col++]);
			//read tasks
		}
		
		for($i=3;$i<count($rows);$i++)
		{
			$col=0;
			$cells = $this->GetCells($rows[$i]);
			$c2 = count($cells);
			if($c1 != $c2)
				return null;
			$milestone = $cells[$col++]->nodeValue;
			$milestone = str_replace(";","",$milestone);
			$duedate =	CTimestamp($this->ReadDate($cells[$col++]));
			
			$bl = new \StdClass();
			$bl->name=$milestone;
			$bl->duedate=$duedate;
			$baseline->milestones[] = $bl;
			$last_cell=$cells[count($cells)-1];
			$completed=0;
			$completedon = CTimestamp($this->ReadDate($last_cell));
			if($completedon == 0)
			{
				if( (strtolower($last_cell->nodeValue) == 'complete')||
					(strtolower($last_cell->nodeValue) == 'completed'))
					$completed=1;
			}
			else
				$completed=1;
			$bl->completed=$completed;
			$bl->completedon=$completedon;

			foreach($pcrs as $pcr)
			{
				$bl = new \StdClass();
				$bl->name=$milestone;
				$duedate =	CTimestamp($this->ReadDate($cells[$col++]));
				$bl->duedate=$duedate;
				$bl->completed=$completed;
				$bl->completedon=$completedon;
				$pcr->milestones[] = $bl;
			}
		}
		$pcrnum = 1;
		$tincomplete=0;
		$tcomplete=0;
		foreach($pcrs as $pcr)
		{
			$incomplete=0;
			$complete=0;
			$this->query = 'key in ('.$pcr->jiraid.')';
			$jtasks = $this->JiraSearch($this->query);
			$pcr->jira = $jtasks[$pcr->jiraid];
			$incomplete=0;
			foreach($pcr->tasks as $task)
			{
				if($task->status != 'complete') 
				{
					$incomplete++;
					$tincomplete++;
				}
				else
				{
					$complete++;
					$tcomplete++;
				}
				$task->assignee = $this->FetchUser($task->riuserkey);
			}
			$pcr->complete=$complete;
			$pcr->incomplete=$incomplete;
		}
		$data =  new \StdClass();
		$data->baseline = $baseline;
		$data->pcrs = $pcrs;
		$data->category = $category;
		$data->name=$name;
		$data->pm = $pm;
		$data->incomplete = $tincomplete;
		$data->complete = $tcomplete;
		
		if($tincomplete==0)
			$data->closed=1;
		else
			$data->closed=0;
		return $data;
	}
	public function ProcessReleasePlanPage($project)
	{
		$xml = $this->FetchPage($project);
		$tables = $this->FetchTables($xml);
		$project->releaseplans = [];
		
		$i=1;
		foreach($tables as $table)
		{
			$type = $this->Identify($table);
			if($type == 'RELEASE PLAN')
			{
				$releaseplan = $this->GetReleasePlans($table);
				if($releaseplan!=null)
				{
					$releaseplan->url = $project->url;
					$project->releaseplans[] = $releaseplan;
				}
				else
				{
					$msg = $project->id."  ".$project->title." #".$i." is invalid";
					echo "\033[93m $msg \n";
					
				}
				$i++;
			}
		}
		return $project;
	}
	public function FetchReleasePlans($all=0)
	{
		$query=[];
		if($all == 0)
			$query=["releaseplans.closed"=> 0];
		
		$projects = $this->db->releaseplan->find($query);
		
		return $projects;
	}
	public function NotificationsForPCRApproval($releaseplan)
	{
		$pcrnum = 1;
		$pcrs = $releaseplan->pcrs;
		
		foreach($pcrs as $pcr)
		{
			$this->query = 'key in ('.$pcr->jiraid.')';
			$jtasks = $this->FetchJiraTickets();
			$pcr->jira = $jtasks[$pcr->jiraid];
			foreach($pcr->tasks as $task)
				$task->assignee = $this->FetchUser($task->riuserkey);
			
			$incomplete=0;
			$complete=0;
			foreach($pcr->tasks as $task)
			{
				if($task->status != 'complete')	
				{
					$incomplete++;
				}
				else
					$complete++;
			}
			if(($incomplete > 0)&&($complete>0))
			{
				$sec = 9999999;
				if(isset($pcr->emailedon))
				{
					$now = Timestamp();;
					$sec = GetBusinessSeconds(CDate(null,$pcr->emailedon),CDate(null,$now),9,18);
					//dump("Business seconds = ".SecondsToString($sec,9));
				}
				if(($sec >= (32400*2))||($this->options['email_resend']==1)) 
				{
					$data = $this->HtmlFormatForPCR($releaseplan->url,$releaseplan->baseline,$pcrs,$pcrnum,$incomplete);
					$data['cc'][]=$this->admin_email;
					foreach($releaseplan->pm as $pm)
						$data['cc'][]= $this->FetchUser($pm)->emailAddress;
					$this->SendMail($data['msg'],$data['to'],$data['cc'],$releaseplan->baseline->project.' - PCR Approval - Pending',true);
					if($this->options['email']==1)
						$pcr->emailedon = Timestamp();
				}
				else
					dump('Email not due : '.$incomplete.'/'.($incomplete+$complete).' pending for PCR#'.$pcrnum.' for '.$releaseplan->category.":".$releaseplan->name." [".SecondsToString($sec,9)."]");
			
			}
			else
			{
				$msg = $incomplete.'/'.($incomplete+$complete).' pending for PCR#'.$pcrnum." for ".$releaseplan->category.":".$releaseplan->name;
			}
			$pcrnum++;
		}
	}
	public function HtmlFormatForPCR($curl,$baseline,$pcrs,$pcrnum,$pending)
	{
		$i=$pcrnum;
		foreach($pcrs as $p)
		{
			$pcr = $p;
			$i--;
			if($i==0)
				break;
		}	
		$url = parse_url($pcr->jira->self);
		$jira_url = $url['scheme']."://".$url['host']."/browse/".$pcr->jiraid;
		//$cc[] = $pcr->jira->assignee['emailAddress'];
		$msg = '<h3> There are <span style="color:red">'.$pending.'</span> pending approvals for <a href="'.$jira_url.'">PCR#'.$pcrnum.'</a> for <span style="color:green">'.$baseline->project.'</span> project</h3>';	
		$msg .= '<table width="100%" cellspacing="0" cellpadding="0">';
		$msg .= '<tr><td><table cellspacing="0" cellpadding="0"><tr>';
        $msg .= '<td style="border-radius: 2px;" bgcolor="#228B22">';
        $msg .= '<a href="'.$curl.'" target="_blank" style="padding: 8px 12px; border: 1px solid #00FF00;border-radius: 2px;font-family: Helvetica, Arial, sans-serif;font-size: 14px; color: #ffffff;text-decoration: none;font-weight:bold;display: inline-block;">';
        $msg .= 'Approve</a></td></tr></table></td></tr></table><br>';
		
		$msg .= '<table style="border: 1px solid black;">';
		$msg .= '<tr style="border: 1px solid black;">';
		$msg .= '<th style="border: 1px solid black;text-align:center">Milestone</th>';
		$msg .= '<th style="border: 1px solid black;text-align:center;color:#F8F8FF;background-color:#A9A9A9">Baseline</th>';
		$i=1;
		foreach($pcrs as $p)
		{
			if($i == $pcrnum)
				$msg .= '<th style="border: 1px solid black;text-align:center;background-color:#7FFF00">PCR - '.$i.'</th>';
			else
				$msg .= '<th style="border: 1px solid black;text-align:center;color:#F8F8FF;background-color:#A9A9A9">PCR - '.$i.'</th>';
			$i++;
			if($i>$pcrnum)
				break;
		}
		$msg .= '</tr>';
		
		
		$j=1;
		for($i=0;$i<count($baseline->milestones);$i++)
		{
			$bl_milestone = $baseline->milestones[$i];
			$msg .= '<tr style="border: 1px solid black;">';
			if($bl_milestone->completed)
				$msg .= '<td style="border: 1px solid black;text-align:left;background-color:#CDCDCD">'.$bl_milestone->name.'</td>';
			else
				$msg .= '<td style="border: 1px solid black;text-align:left;background-color:#F0F8FF">'.$bl_milestone->name.'</td>';
			
			$duedate = '';
			if($bl_milestone->duedate > 0)
				$duedate=$this->TimestampToObj($bl_milestone->duedate)->format('M d, Y');
			$msg .= '<td style="border: 1px solid black;text-align:left">'.$duedate.'</td>';
			$j=1;
			$prev = $bl_milestone->duedate;
			foreach($pcrs as $pcr)
			{
				$pcr_milestone = $pcr->milestones[$i];
				$duedate = '';
				if($pcr_milestone->duedate > 0)
				{
					$duedate=$this->TimestampToObj($pcr_milestone->duedate)->format('M d, Y');
					if($pcr_milestone->duedate != $prev)
					{
						if($j == $pcrnum)
							$msg .= '<td style="border: 1px solid black;text-align:left;background-color:#FFD700">'.$duedate.'</td>';
						else
							$msg .= '<td style="border: 1px solid black;text-align:left;background-color:#FFFFE0">'.$duedate.'</td>';

					}
					else
						$msg .= '<td style="border: 1px solid black;text-align:left">'.$duedate.'</td>';
				}
				else
					$msg .= '<td style="border: 1px solid black;text-align:left">'.$duedate.'</td>';
				
				$j++;
				if($j>$pcrnum)
					break;
				$prev = $pcr_milestone->duedate;
			}
			$msg .= '</tr>';
		}
		
		$msg .= '</table>';
		$msg .= '<h4>Approval Status</h4>';
		$msg .= '<table>';
		$to = [];
		$cc = [];
		//$cc[]=$pcr->tasks[0]->assignee->emailAddress;
		$tasks = $pcr->tasks->jsonSerialize();
			usort($tasks, function($a, $b){
				return strcmp($b->status,$a->status);
			});
		$pcr->tasks = $tasks;
		foreach($pcr->tasks as $task)
		{
			$msg .= '<tr>';
			if($task->status == 'complete')
			{
				$msg .= '<td><img  src="cid:checkmark.png" alt="star" width="16" height="16"></td>';
				$msg .= '<td><span style="color:green;">'.$task->assignee->displayName."&nbsp&nbsp</span></td>";

			}
			else
			{
				$to[]=$task->assignee->emailAddress;
				$msg .= '<td><img src="cid:incomplete.jpg" alt="star" width="16" height="16"></td>';
				$msg .= '<td><span style="color:orange;">'.$task->assignee->displayName."&nbsp&nbsp</span></td>";
			}
			$msg .= '</tr>';
		}
		$msg .= '</table><br>';
		$msg .= '<br><br><hr>';
	    $msg .= '<small style="margin: auto;">This is an automatically generated email, please do not reply. You are getting this email because you are in stakeholders list</small><br>';
		return ['msg'=>$msg,'to'=>$to,'cc'=>$cc];
	}
	////////////////////////////////////////////////////////////////////////
	public function GetPORApprovals($table)
	{	
		$xmlDoc = new \DOMDocument();
		@$xmlDoc->loadXML($table);
		
		$rows = $this->GetTblRows($xmlDoc);
		if(count($rows)==0)
			return null;
		
		$cells = $this->GetCells($rows[0]);
		if(count($cells)<3)
			return null;
		$name = $cells[1]->nodeValue;
		$pm = $this->ReadUsers($cells[2]);
		
		$tasks = $this->ReadTasks($xmlDoc);
		
		$incomplete=0;
		$complete=0;
		foreach($tasks as $task)
		{
			if($task->status != 'complete') 
				$incomplete++;
			else
				$complete++;
			$task->assignee = $this->FetchUser($task->riuserkey);
		}
		$data =  new \StdClass();
		$data->tasks = $tasks;
		$data->name = $name;
		$data->pm = $pm;
		$data->incomplete = $incomplete;
		$data->complete = $complete;
		if($incomplete==0)
			$data->closed=1;
		else
			$data->closed=0;
		return $data;
	}
	public function ProcessPORApprovalPage($project)
	{
		$xml = $this->FetchPage($project);
		$tables = $this->FetchTables($xml);
		$project->porapprovals = [];
		$i=1;
		foreach($tables as $table)
		{
			$type = $this->Identify($table);
			if($type == 'POR SIGN OFF')
			{
				$porapproval = $this->GetPORApprovals($table);
				if($porapproval!=null)
				{
					$porapproval->url = $project->url;
					$project->porapprovals[] = $porapproval;
				}
				else
				{
					$msg = $project->id."  ".$project->title." #".$i." is invalid";
					echo "\033[93m $msg \n";
				}
				$i++;
			}
		}
		return $project;
	}
	public function FetchPorApprovals($all=0)
	{
		$query=[];
		if($all == 0)
			$query=["porapprovals.closed"=> 0];
		
		$projects = $this->db->porapproval->find($query);
		
		return $projects;
	}
	public function HtmlFormatForPORSignoff($url,$tasks,$incomplete,$project)
	{
		$msg = '<h3> There are <span style="color:red">'.$incomplete.'</span> pending approvals for <span style="color:green">'.$project.'</span> project POR</h3>';	
		$msg .= '<table width="100%" cellspacing="0" cellpadding="0">';
		$msg .= '<tr><td><table cellspacing="0" cellpadding="0"><tr>';
        $msg .= '<td style="border-radius: 2px;" bgcolor="#228B22">';
        $msg .= '<a href="'.$url.'" target="_blank" style="padding: 8px 12px; border: 1px solid #00FF00;border-radius: 2px;font-family: Helvetica, Arial, sans-serif;font-size: 14px; color: #ffffff;text-decoration: none;font-weight:bold;display: inline-block;">';
        $msg .= 'Approve</a></td></tr></table></td></tr></table><br>';
		$cc = [];
		$msg .= '<table>';
		
		$tasks = $tasks->jsonSerialize();
			usort($tasks, function($a, $b){
				return strcmp($b->status,$a->status);
			});
	
		foreach($tasks as $task)
		{
			$msg .= '<tr>';
			if($task->status == 'complete')
			{
				$msg .= '<td><img  src="cid:checkmark.png" alt="star" width="16" height="16"></td>';
				$msg .= '<td><span style="color:green;">'.$task->assignee->displayName."&nbsp&nbsp</span></td>";

			}
			else
			{
				$to[]=$task->assignee->emailAddress;
				$msg .= '<td><img src="cid:incomplete.jpg" alt="star" width="16" height="16"></td>';
				$msg .= '<td><span style="color:orange;">'.$task->assignee->displayName."&nbsp&nbsp</span></td>";
			}
			$msg .= '</tr>';
		}
		$msg .= '</table>';
		
		$msg .= '<br><br><hr>';
	    $msg .= '<small style="margin: auto;">This is an automatically generated email, please do not reply. You are getting this email because you are in stakeholders list</small><br>';
		
		return ['msg'=>$msg,'to'=>$to,'cc'=>$cc];
	}
	public function NotificationsForPORApproval($porapproval)
	{		
		$incomplete=0;
		$complete=0;
		foreach($porapproval->tasks as $task)
		{
			if($task->status != 'complete') 
				$incomplete++;
			else
				$complete++;
			$task->assignee = $this->FetchUser($task->riuserkey);
		}	
		if((($incomplete > 0)&&($complete>0)))
		{
			$sec = 9999999;
			if(isset($porapproval->emailedon))
			{
				$now = $this->CurrentDateTime();
				$sec = GetBusinessSeconds(CDate(null,$porapproval->emailedon),CDate(null,$now),9,18);
				dump("Business seconds = ".SecondsToString($sec,9));
			}
			if(($sec >= (32400*2))||($this->options['email_resend']==1)) 
			{
				$data = $this->HtmlFormatForPORSignoff($porapproval->url,$porapproval->tasks,$incomplete,$porapproval->name);
				$data['cc'][]=$this->admin_email;
				foreach($porapproval->pm as $pm)
					$data['cc'][]= $this->FetchUser($pm)->emailAddress;
			
				$this->SendMail($data['msg'],$data['to'],$data['cc'],$porapproval->name.' - POR Sign-off - Pending',true);
				if($this->options['email']==1)
					$porapproval->emailedon = $this->CurrentDateTime();
			}
			else
				dump('Email not due : There are '.$incomplete.'/'.($incomplete+$complete).' pending POR approvals for this project');
		}
		else
		{
			dump('Project not started');
			dump('There are '.$incomplete.'/'.($incomplete+$complete).' pending POR approvals for this project');
		}
		
	}
	public function ReleasePlanCopyBack($project,$sproject)
	{
		$rlpapproval_emailedon = [];
		foreach($sproject->releaseplans as $releaseplan)
		{
			foreach($releaseplan->pcrs as $pcr)
			{
				if(isset($pcr->emailedon))
					$rlpapproval_emailedon[$pcr->jiraid]=$pcr->emailedon;
				else
					$rlpapproval_emailedon[$pcr->jiraid]=0;
			}
		}
		foreach($project->releaseplans as $releaseplan)
		{
			foreach($releaseplan->pcrs as $pcr)
			{
				if(isset($rlpapproval_emailedon[$pcr->jiraid]))
					$pcr->emailedon=$rlpapproval_emailedon[$pcr->jiraid];
			}
		}
	}
	public function PorApprovalsCopyBack($project,$sproject)
	{
		$pcrapproval_emailedon = [];
		foreach($sproject->porapprovals as $porapproval)
		{
			if(isset($porapproval->emailedon))
				$pcrapproval_emailedon[$porapproval->name]=$porapproval->emailedon;
			else
				$pcrapproval_emailedon[$porapproval->name]=0;
		
		}
		foreach($project->porapprovals as $porapproval)
		{
			if(isset($pcrapproval_emailedon[$porapproval->name]))
				$porapproval->emailedon = $pcrapproval_emailedon[$porapproval->name];
		}
	}
	public function CheckBaselineDiffReleasePlans($project)
	{
		$sproject = $this->Read($project->id,'baseline');
		if($sproject == null)
		{
			$msg = $project->id.'  "'.$project->title.'" is added recently';
			echo "\033[93m $msg \n";
			return;
		}
		for($i=0;$i<count($project->releaseplans);$i++)
		{
			if(!isset($sproject->releaseplans[$i]))
			{
				$msg=$project->id."  release plan in ".$project->releaseplans[$i]->category.":".$project->releaseplans[$i]->name."  is added recently";
				echo "\033[93m $msg \n";
				continue;
			}	
			for($j=0;$j<count($project->releaseplans[$i]->baseline->milestones);$j++)
			{
				$name=$project->releaseplans[$i]->name;
				$category=$project->releaseplans[$i]->category;
				
				if(!isset($sproject->releaseplans[$i]->baseline->milestones[$j]))
				{
					$msg=$project->id.":".$category.":".$name." milestone#=".$j." is added recently";
					echo "\033[93m $msg \n";
					break;
				}
				
				if($project->releaseplans[$i]->baseline->milestones[$j]->name !=
				$sproject->releaseplans[$i]->baseline->milestones[$j]->name)
				{
					$msg=$project->id.":".$category.":".$name." milestone#".$j." name is updated";
					echo "\033[93m $msg \n";
				}
				if($project->releaseplans[$i]->baseline->milestones[$j]->duedate !=
				$sproject->releaseplans[$i]->baseline->milestones[$j]->duedate)
				{
					$msg=$project->id.":".$category.":".$name." milestone#".$j." baseline duedate changed";
					echo "\033[93m $msg \n";
				}
			}
			foreach($project->releaseplans[$i]->pcrs as $key=>$pcr)
			{
				$name=$project->releaseplans[$i]->name;
				$category=$project->releaseplans[$i]->category;
				
				if(!isset($sproject->releaseplans[$i]->pcrs[$key]))
				{
					$msg=$project->id.":".$category.":".$name.":".$key."  is added recently";
					echo "\033[93m $msg \n";
					continue;
				}
				for($j=0;$j<count($pcr->milestones);$j++)
				{
					if($project->releaseplans[$i]->pcrs[$key]->milestones[$j]->duedate !=
					$sproject->releaseplans[$i]->pcrs[$key]->milestones[$j]->duedate)
					{
						$msg=$project->id.":".$category.":".$name.":".$key." milestone#".$j." baseline duedate changed";
						echo "\033[93m $msg \n";
					}
				}
			}
		}
		//$project = $this->Read(db->baseline->findOne(['id'=>$project->id]);
		//if($project !== null)
		//{
			
		//}
	}
	public function CheckInactivePORApprovals($updatedon)
	{
		$uprojects = $this->db->porapproval->find(['porapprovals.updatedon'=>['$ne'=>$updatedon]]);	
		if($uprojects != null)
		{
			foreach($uprojects as $uproject)
			{
				foreach($uproject->porapprovals as $porapproval)
				{
					if(!isset($porapproval->updatedon))
					{
						$msg = $uproject->id."  ".$porapproval->name." is inactive POR now";
						echo "\033[93m $msg \n";
					}
					else if($porapproval->updatedon != $updatedon)
					{
						$msg = $uproject->id."  ".$porapproval->name." is inactive POR now";
						echo "\033[93m $msg \n";
					}
				}
			}
		}
	}
	public function DeleteReleaseplan()
	{
		$id=$this->options['id'];
		$name=$this->options['name'];
		if($id == 0)
			dd($id.' invalid id');
		$projects = $this->db->releaseplan->find(['id'=>$id]);	
		
		foreach($projects as $project)
		{
			$i=0;
			$found=0;
			foreach($project->releaseplans as $releaseplan)
			{
				dump($name." ".$releaseplan->name);
				if($name == $releaseplan->name)
				{
					$found=1;
					break;
				}
				$i++;
			}
			if($found)
				unset($project->releaseplans[$i]);
			else
			{
				dd($name.' invalid name');
			}
		}
		if(count($project->releaseplans) > 0)
		{
			$this->Save($project,'releaseplan');
			dump('Release plan Removed');
		}
		else
		{
			$this->db->releaseplan->deleteOne(['id'=>$project->id]);
			dump('Project Removed');
		}
	}
	public function DeletePorApproval()
	{
		$id=$this->options['id'];
		$name=$this->options['name'];
		if($id == 0)
			dd($id.' invalid id');
		$projects = $this->db->porapproval->find(['id'=>$id]);	
		foreach($projects as $project)
		{
			$i=0;
			$found=0;
			foreach($project->porapprovals as $porapproval)
			{
				if($name == $porapproval->name)
				{
					$found=1;
					break;
				}
				$i++;
			}
			if($found)
				unset($project->porapprovals[$i]);
			else
			{
				dd($name.' invalid name');
			}
		}
		if(count($project->porapprovals) > 0)
		{
			$this->Save($project,'porapproval');
			dump('POR Removed');
		}
		else
		{
			$this->db->porapproval->deleteOne(['id'=>$project->id]);
			dump('Project Removed');
		}
	}
	public function CheckInactiveReleasePlans($updatedon)
	{
		$uprojects = $this->db->releaseplan->find(['releaseplans.updatedon'=>['$ne'=>$updatedon]]);	
		if($uprojects != null)
		{
			foreach($uprojects as $uproject)
			{
				foreach($uproject->releaseplans as $releaseplan)
				{
					if(!isset($releaseplan->updatedon))
					{
						$msg = $uproject->id." ".$releaseplan->name."  release plan is inactive";
						echo "\033[93m $msg \n";
					}
					else if($releaseplan->updatedon != $updatedon)
					{
						$msg = $uproject->id."  ".$releaseplan->name."  release plan is inactive";
						echo "\033[93m $msg \n";
					}
				}
			}
		}
	}
	public function NotifyBaselineChanges()
	{
		$id = $this->options['id'];
		$update = $this->options['update'];
		$projects = $this->FetchReleasePlans(1);
		$sproject = null;
		foreach($projects as $project)
		{
			if($id == 0) 
				$this->CheckBaselineDiffReleasePlans($project);
			else if($id == $project->id) 
			{
				$this->CheckBaselineDiffReleasePlans($project);
				$sproject = $project;
			}
		}
		if(($sproject == null)&&($id >0))
			dump('Not found');
		else if(($sproject != null)&&($update==1))
		{
			unset($project->_id);
			$this->Save($project,'baseline');
			dump("Updated");
			
		}
		dump("Done");
	}
	public function Script()
	{
		dump("Running script");	
		$projects = $this->FindPages('por_approval');
		dump("********  Checking POR Sign off   **********");	
		$updatedon = CTimestamp();
		foreach($projects as $project)
		{
			$project = $this->ProcessPORApprovalPage($project);
			$sproject = $this->db->porapproval->findOne(['id'=>$project->id]);	
			
			foreach($project->porapprovals as $porapproval)
			{
				$msg = '';
				if($porapproval->closed)
				{
					$msg = $project->id."  ".$porapproval->name."  ".$porapproval->complete."/".($porapproval->complete+$porapproval->incomplete);
					if(($porapproval->incomplete == 0)&&($porapproval->complete == 0))
						echo "\033[39m $msg \n";
					else
						echo "\033[92m $msg \n";
				}
				else
				{
					$msg =  $project->id."  ".$porapproval->name."  ".$porapproval->complete."/".($porapproval->complete+$porapproval->incomplete);
					if($porapproval->complete > 0)
						echo "\033[91m $msg \n";
					else
						echo "\033[39m $msg \n";
						//echo "\033[31m $msg \n";
				}
				$porapproval->updatedon = $updatedon;
			}
			if($sproject == null)
				$this->PorApprovalsCopyBack($project,$project);
			else
				$this->PorApprovalsCopyBack($project,$sproject);
			
			$project->updatedon = $updatedon;
			$this->Save($project,'porapproval');
		}
		$this->CheckInactivePORApprovals($updatedon);
		dump("********** Checking Release Plans *************");
		$projects = $this->FindPages('release_plan');
		foreach($projects as $project)
		{
			//if($project->id != 206898045)
			//	continue;
			$project = $this->ProcessReleasePlanPage($project);
			$sproject = $this->db->releaseplan->findOne(['id'=>$project->id]);	
			foreach($project->releaseplans as $releaseplan)
			{
				
				$msg = '';
				if($releaseplan->closed)
				{
					$msg = $project->id."  ".$releaseplan->category.":".$releaseplan->name."  ".$releaseplan->complete."/".($releaseplan->complete+$releaseplan->incomplete);
					//echo "\033[39m $msg \n";
					if(($releaseplan->incomplete == 0)&&($releaseplan->complete == 0))
						echo "\033[39m $msg \n";
					else
						echo "\033[92m $msg \n";
				}
				else
				{
					$msg = $project->id."  ".$releaseplan->category.":".$releaseplan->name."  ".$releaseplan->complete."/".($releaseplan->complete+$releaseplan->incomplete);
					if($releaseplan->complete > 0)
						echo "\033[91m $msg \n";
					else
						echo "\033[39m $msg \n";
				}
				$releaseplan->updatedon = $updatedon;
			}
			if($project->id == 206898045)
			{
				
				foreach($project->releaseplans[0]->pcrs as $key=>$pcr)
				{
					dump($key);
					foreach($pcr->milestones as $ms)
					{
						$dt =  CDateTime($ms->duedate);
						dump($ms->name."  ".$ms->duedate."  ".$dt->format('Y-m-d'));
					}
				}
				
			}
			if($sproject == null)
				$this->ReleasePlanCopyBack($project,$project);
			else
				$this->ReleasePlanCopyBack($project,$sproject);
			
			$project->updatedon = $updatedon;
			$this->Save($project,'releaseplan');
			
		}
		$this->CheckInactiveReleasePlans($updatedon);
		
	}
}