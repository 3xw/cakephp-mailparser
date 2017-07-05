# cakephp-parser plugin for CakePHP
<p align="left">
    <a href="LICENSE.txt" target="_blank">
        <img alt="Software License" src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square">
    </a>
</p>
Use this plugin to connect a mail account retrieve mail(s) & related attachment(s).
A base Parser class is present in order to parse csv/xls files and store data in your db via your models.

this plugin comes with very usefull libs:

	"dereuromark/cakephp-queue": "^3.4",
    "php-imap/php-imap": "~2.0",
    "league/csv": "^8.2",
    "phpoffice/phpexcel": "^1.8"

## Installation

You can install this plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

	composer require 3xw/cakephp-mailparser

Load it in your config/boostrap.php

	not needed
	
## Workers
### SaveMailAttachments queue task
based on [dereuromark/cakephp-queue](https://github.com/dereuromark/cakephp-queue) you can directly use the SaveMailAttachments task.
You can add the task to workers list:

	$this->QueuedJobs->createJob('SaveMailAttachments', [
		'name' => 'Save mail attachments',
		'data' => [
			'username' => 'username',
			'password' => 'password',
			'mailbox' => '{imap.example.org:993/imap/ssl}INBOX',
			'mailObjectNeedles' => ['test','exemple'], 	// mail objects array to look for
			'fileNeedles' => ['.*\.xlsx'], 				// file serach strings array based on cakephp's Folder::find() fct
			'folder' => TMP.'MailAttachments/' 			// folder to copy files in. ( be sure folder exists if php is not allowed to create the very folder )
		]
	]);
	
### Extends the SaveMailAttachments queue task
Find here a exemple task that extends SaveMailAttachments Task:

	<?
	namespace App\Shell\Task;
	
	use App\Parser\CsvToModelParser; // a custom parser that extends Trois\MailParser\Parser\Parser
	use Trois\MailParser\Shell\Task\QueueSaveMailAttachmentsTask;
	
	class QueueGetLastCsvTask extends QueueSaveMailAttachmentsTask
	{
	  public $defaults = [
		'username' => 'xxx',
		'password' => 'xx',
		'mailbox' => '{mail.xxx.com:993/imap/ssl}INBOX',
		'mailObjectNeedles' => ['Object 1','Object 2'],
		'fileNeedles' => ['.*\.csv'],
		'folder' => TMP.'foo/' // needed even if not used...
		];
	
	  public $parser = null;
	
	  public function run(array $data, $id)
	  {
	    $this->_connect();
	    $this->parser = new CsvToModelParser();
	    $count = 0;
	    $rows = 0;
	    $success = 0;
	    foreach($this->defaults['mailObjectNeedles'] as $needle)
	    {
	      $files = $this->_getMailAttachments($needle);
	      foreach($files as $key => $file)
	      {
	        $count++;
	        $this->out('proccessing file: '.$key.' -> '.$file->name);
	        $results = $this->parser->save($file);
	        if(empty($results))
	        {
	          $this->err('Error parsing file -> No record saved');
	        }else{
	          $rows += count($results);
	          $this->info(count($results).' positions were successfully stored!');
	          $success++;
	        }
	      }
	      $this->_clean();
	    }
	
	    // inform
	    $status = $success.' files were successfully parsed on '.$count.', '.$rows.' positions were stored in db';
	    return $this->_finish($status, $id);
	  }
	}

You can now add this task directly:

	bin/cake Queue add GetLastCsv
	
And run the worker:

	bin/cake Queue runworker
	
## Parsers
The class Trois\MailParser\Parser\Parser will help you parse files. It implements Trois\MailParser\Parser\iParser interface that is as folow:

	<?
	namespace Trois\MailParser\Parser;
	
	use Cake\Filesystem\File;
	
	interface iParser
	{
	  public function fileToEntities(File $file);
	
	  public function save(File $file);
	}

### Parser class
This class will init models for you and save parsed entities using the main model. Its concept is: file against model:

	<?
	namespace Trois\MailParser\Parser;
	
	use Cake\ORM\TableRegistry;
	use Cake\Filesystem\File;
	use Cake\Core\InstanceConfigTrait;
	
	class Parser implements iParser
	{
	  use InstanceConfigTrait;
	
	  public $reader = null;
	
	  protected $_defaultConfig = [
	    'model' => 'ExempleModelTable',
	    'extraModels' => []
	  ];
	
	  public function __construct(array $config = [])
	  {
	    $this->setConfig($config);
	    $this->initialize($config);
	  }
	
	  public function initialize(array $config)
	  {
	    $model = $this->config('model');
	    $models = $this->config('extraModels');
	    if($models && is_array($models))
	    {
	      $models[] = $model;
	    }else
	    {
	      $models = [$model];
	    }
	    foreach($models as $model)
	    {
	      $this->{$model} = TableRegistry::get($model);
	    }
	  }
	
	  public function fileToEntities(File $file)
	  {
	    return [];
	  }
	
	  public function save(File $file)
	  {
	    $entities = $this->fileToEntities($file);
	    if($entities){
	      return $this->{$this->config('model')}->saveMany($entities);
	    }
	    return [];
	  }
	}

### Extends the Parser class
Find below an exemple to parse a csv file using [league/csv](https://github.com/thephpleague/csv):

	<?
	namespace App\Parser;
	
	use League\Csv\Reader;
	use Cake\Filesystem\File;
	use Trois\MailParser\Parser\Parser;
	
	class CsvToModelParser extends Parser
	{
	  protected $_defaultConfig = [
	    'model' => 'ModelOne',
	    'extraModels' => []
	  ];
	
	  public function fileToEntities(File $file)
	  {
	    $reader = Reader::createFromPath($file->path)->setDelimiter(';');
	    $headers = $reader->fetchOne();
	    $entities = [];
	    foreach($reader->setOffset(1)->fetchAssoc($headers) as $row)
	    {
	    	$entities[] = $row;
	    }
	    return $this->ModelOne->newEntities($entities); // ModelOne was created for you using $_defaultConfig
	  }
	}

As you can see in Extends the SaveMailAttachments queue task, the herited method:

	$this->/*parser->*/save($file);
	
will return the result of

	$YourModel->saveMany($entities);