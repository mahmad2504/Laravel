<?php
namespace App\Console\Commands\Confluence;

use Illuminate\Console\Command;
use App\Apps\Confluence\Confluence;

class Deleteporapproval extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
	protected $signature = 'porapproval:delete {--id=0} {--name=null}';

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
		$app->DeletePorApproval();
    }
}