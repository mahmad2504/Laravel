<?php
namespace App\Console\Commands\Confluence;

use Illuminate\Console\Command;
use App\Apps\Confluence\Confluence;

class Porapproval extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
	protected $signature = 'porapproval:notify {--rebuild=0} {--force=0} {--email=2} {--email_resend=0} {--id=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
	
    public function __construct()
    {
		parent::__construct();
    }
	
    public function handle()
    {
		
		$app = new Confluence($this->option());
		$projects = $app->FetchPorApprovals();
		$sendemail = 0;
		
		foreach($projects as $project)
		{
			if($this->option()['id'] != 0)
			{
				if($this->option()['id'] != $project->id)
					continue;
			}
			
			foreach($project->porapprovals as $porapproval)
			{
				dump($project->id."  ".$porapproval->name);
				$app->NotificationsForPORApproval($porapproval);
				dump("*****************");
			}
			$app->Save($project,'porapproval');
		}
		echo "\033[0m Done \n";
    }
}