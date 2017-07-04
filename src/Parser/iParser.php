<?
namespace Trois\MailParser\Parser;

use Cake\Filesystem\File;

interface iParser
{
  public function fileToEntities(File $file);

  public function save(File $file);
}
