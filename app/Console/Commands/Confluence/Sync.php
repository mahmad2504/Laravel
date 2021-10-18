<?php
namespace App\Console\Commands\Confluence;

use Illuminate\Console\Command;
use App\Apps\Confluence\Confluence;

class Sync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
	protected $signature = 'confluence:sync {--force=0} {--rebuild=0} {--email_resend=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Data From Confluence';

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
		$this->option();
		$app = new Confluence($this->option());
		$app->Run();
    }
}