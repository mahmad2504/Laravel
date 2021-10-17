<?php
namespace App\Console\Commands\Confluence;

use Illuminate\Console\Command;
use App\Apps\Confluence\Confluence;

class Pcr extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
protected $signature = 'pcr:notify {--rebuild=0} {--force=0} {--email=2} {--email_resend=0} {--id=null}';

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
		$projects = $app->FetchReleasePlans();
		foreach($projects as $project)
		{
			foreach($project->releaseplans as $releaseplan)
			{
				$app->NotificationsForPCRApproval($releaseplan);
			}
			$app->Save($project,'releaseplan');
		}
		echo "\033[0m Done \n";
    }
}