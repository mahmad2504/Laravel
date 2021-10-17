<?php
namespace App\Console\Commands\Confluence;

use Illuminate\Console\Command;
use App\Apps\Confluence\Confluence;

class Baseline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
	protected $signature = 'baseline:notify {--update=0} {--rebuild=0} {--force=0} {--id=0}';

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
		$app->NotifyBaselineChanges();
    }
}