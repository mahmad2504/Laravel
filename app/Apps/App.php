<?php
namespace App\Apps;
use App\Libs\Utility\Email;
use \MongoDB\Client;
use \MongoDB\BSON\UTCDateTime;
use App\Libs\Jira\Jira;
use App\Libs\Jira\Fields;
use Carbon\Carbon;
class App
{
	public $key = 'Unknown';
	public $app = null;
	public $jira_fields = [];
	public $jira_customfields = [];
	public $timezone =  null;
	public $mongo = null;
	public $fields = null;
	public $scriptname = 'app';
	public $admin_email = null;
	public function InitOption()
	{
		if(!isset($this->options))
			$this->options = [];
		if(!isset($this->options['rebuild']))
			$this->options['rebuild'] = 0;
		if(!isset($this->options['force']))
			$this->options['force'] = 0;
		if(!isset($this->options['email']))
			$this->options['email'] = 2;
		if(!isset($this->options['email_resend']))
			$this->options['email_resend'] = 0;
		if(!isset($this->options['dbname']))
			$this->options['dbname']=null;
		
	}
	public function InConsole($yes)
	{
		// Child should override this to see if application is running in console or from web
	}
	
	public function __construct($app)
	{
		$this->InitOption();
		$this->admin_email = env("ADMIN_EMAIL");
		$this->app = $app;
		$this->datafolder = env("INSTALL_FOLDER").$this->datafolder;
		
		if(app()->runningInConsole())
		{
			$app->InConsole(true);
		}
		else
			 $app->InConsole(false);
		 
		if(!isset($this->namespace))
			$this->namespace = __NAMESPACE__;
			
		$parts = explode("\\",$app->namespace);
		$key = strtolower($parts[count($parts)-1]);
		$this->key = $key;
		if($this->options['dbname'] == null) 
			$this->dbname=$key;
		else
			$this->dbname=$this->options['dbname'];
		
		if(isset($this->timezone))
			date_default_timezone_set($this->timezone);
		
		//if(!isset($this->mongo_server))
		//	dd("App mongo_server not set");
		
		if(isset($this->mongo_server))
		{
			$mongoclient =new Client($this->mongo_server);
			$this->mongo = $mongoclient;
			$dbname = $this->dbname;
			$this->db = $mongoclient->$dbname;
		}
		
		if(isset($this->jira_server))
		{
			$this->fields = new Fields($this);
			Jira::Init($app);
			if(!$this->fields->Exists()||$this->options['rebuild'])
			{
				dump("Configuring Jira Fields");
				$this->fields = new Fields($this,0);
				$this->fields->Set($this->jira_fields);
				if($this->isAssoc($this->jira_customfields))
					$this->fields->Set($this->jira_customfields);
				$this->fields->Dump();
			}
		}
	}
	private function isAssoc(array $arr)
	{
		if (array() === $arr) return false;
		return array_keys($arr) !== range(0, count($arr) - 1);
	}
	function IssueParser($code,$issue,$fieldname)
	{
		dd("Implement IssueParser function");
	}
	public function JiraFields(&$fields,&$customfields)
	{
		dd("Implement JiraFields function");
	}
	public function Rebuild()
	{
		dump('Rebuild callback function not implemented');
	}
	public function Options()
	{
		$options = [];
		foreach($this->options as $key=>$value)
		{
			if(($key == 'help')||
			   ($key == 'version')||
			   ($key == 'version')||
			   ($key == 'quiet')
			   )
				continue;
			$options["--".$key] = $value;
		}
		return $options	;	
	}
	public function Run()
	{
		if($this->app->TimeToRun())
		{
			if($this->options['rebuild'])
				$this->Rebuild();
			dump("#########  Running script ".$this->scriptname."  #########");
			
			$this->Script();
			$this->SaveUpdateTime();
			$this->Save([$this->scriptname.'_sync_requested'=>0]);
			
			$sec = $this->SecondsSinceLastUpdate();
			$error_sync = $this->Read($this->scriptname.'_error_sync');
			if($error_sync==1)
			{
				$subject = "Service Status Alert : ".strtoupper($this->scriptname)." : Up ";
				$this->NotifyAdmin('Service '.$this->scriptname." is restored",$subject);
				$this->Save([$this->scriptname.'_error_sync'=>0]);
			}
			dump("Done");
		}
	}
	public function Script()
	{
		dd("Implement Script function");
	}
	public function IsSyncRequested()
	{
		$val = $this->Read($this->scriptname.'_sync_requested');
		if($val == 0)
			return 0;
		return 1;
	}
	public function RequestSync()
	{
		$this->Save([$this->scriptname.'_sync_requested'=>1]);
	}
	public function ClearSyncRequest()
	{
		$this->Save([$this->scriptname.'_sync_requested'=>0]);
	}
	public function TimeToRun($update_every_xmin=1)
	{
		$sync_requested = $this->Read($this->scriptname.'_sync_requested');
		if($this->options['rebuild']||$this->options['force']||$sync_requested)
			return true;
		
		$now = CDateTime();
		if($now->isWeekend())
			return false;

		$sec = $this->SecondsSinceLastUpdate();
		if($sec == null)
		{
			$this->SaveUpdateTime();
			return true;
		}
		
		if($sec >=  $update_every_xmin*3*60)
		{
			$timeout=$this->Read($this->scriptname.'_timeout');
			$min_since_last_update = round($sec/60);
			dump("Timeout #".$timeout."  [".$min_since_last_update."] minutes gone since last update. Update time out is ".($update_every_xmin*60)." seconds");
			if($min_since_last_update > 1440)
			{
				$timeout=0;
				$this->SaveUpdateTime();
				$this->Save([$this->scriptname.'_timeout'=>$timeout]);
			}
			if($timeout==null)
				$timeout=0;
			if($timeout==2)
			{
				$subject = "Service Status Alert : ".strtoupper($this->scriptname)." : Down ";
				$this->NotifyAdmin('Service '.$this->scriptname.' has some issue and not updating',$subject);
				dump("Sending Service Error Notification [".round($sec/60)."] minutes gone since last update. Update time out is ".($update_every_xmin*60)." seconds");
				$timeout++;
				$this->Save([$this->scriptname.'_timeout'=>$timeout]);
				$this->Save([$this->scriptname.'_error_sync'=>1]);
				return false;
			}
			else
			{
				$timeout++;
				//dump("timeout #".$timeout);
				$this->Save([$this->scriptname.'_timeout'=>$timeout]);
				return true;
			}
		}
		if($sec >=  $update_every_xmin*60)
		{
			$timeout = 0;
			$this->Save([$this->scriptname.'_timeout'=>$timeout]);
			dump("Updating after [".round($sec/60)."] minutes");
			return true;
		}
		dump("Its not time to update.[".$sec."] seconds gone since last update.Update time out is ".($update_every_xmin*60)." seconds");
		return false;
	}
	public function NotifyAdmin($msg,$subject,$to=null)
	{
		$email = new Email();	
		if($to == null)
			$email->Send(2,$subject,$msg);
		else
			$email->Send(1,$subject,$msg,$to);
	}
	public function SecondsSinceLastUpdate()
	{
		if($this->ReadUpdateTime()==null)
			return null;
		$ldt = new \DateTime($this->ReadUpdateTime());
		$ldt = $ldt->getTimestamp();
		$cdt = CTimeStamp();
		return $cdt - $ldt;
	}
	public function SetTimeZone($datetime)
	{
		$datetime->setTimezone(new \DateTimeZone($this->timezone));
	}
	function Save($obj,$col=null)
	{
		if($col == null)
			$col = 'settings';
		
		$options=['upsert'=>true];
		if(is_array($obj))
		{
			foreach($obj as $key=>$value)
			{
				$query=['id'=>$key];
				$o = new \StdClass();
				$o->id = $key;
				$o->_value=$value;
				if(isset($this->db))
					$this->db->$col->updateOne($query,['$set'=>$o],$options);
				else
					file_put_contents($this->datafolder."/".$key,json_encode($o));
			}
		}
		else
		{
			$query=['id'=>$obj->id];
			if(isset($this->db))
				$this->db->$col->updateOne($query,['$set'=>$obj],$options);
			else
				file_put_contents($this->datafolder."/".$obj->id,json_encode($obj));
		}
	}
	function Delete($id,$col=null)
	{
		if($col == null)
			$col = 'settings';
		$query=['id'=>$id];
		$obj = $this->db->$col->deleteOne($query);
	}
	function Read($id,$col=null)
	{
		if($col == null)
			$col = 'settings';
		
		$query=['id'=>$id];
		if(isset($this->db))
			$obj = $this->db->$col->findOne($query);
		else
		{
			if(!file_exists($this->datafolder."/".$id))
				return null;
			$obj = file_get_contents($this->datafolder."/".$id);
			$obj = json_decode($obj);
		}
		if($obj == null)
			return null;
		if(isset($this->db))
			$obj =  $obj->jsonSerialize();
		if(isset($obj->_value))
			return $obj->_value;
		unset($obj->_id);
		return $obj;
	}
	public function SaveUpdateTime($id=null)
	{
		if($id==null)
			$id=$this->scriptname.'_lastupdate';
		$obj = new \StdClass();
		$obj->date =  new \DateTime();
		$obj->date = $obj->date->format('Y-m-d H:i:s');
		$obj->id = $id;
		$lastupdate = $this->Save($obj);
	}
	public function ReadUpdateTime($id=null)
	{
		if($id==null)
			$id=$this->scriptname.'_lastupdate';
		
		$ud = $this->Read($id);
		if($ud ==  null)
			return null;
		else
			return $ud->date;
	}
	function JiraSearch($jql=null)
	{
		
		if($jql==null)
			return Jira::FetchTickets($this->query);
		else
			return Jira::FetchTickets($jql);
	}
}