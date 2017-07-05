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
