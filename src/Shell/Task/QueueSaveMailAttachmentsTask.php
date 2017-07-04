<?php

namespace Trois\MailParser\Shell\Task;

use PhpImap\Mailbox as ImapMailbox;
use PhpImap\IncomingMail;
use PhpImap\IncomingMailAttachment;
use Cake\Filesystem\Folder;
use Cake\Filesystem\File;
use Queue\Shell\Task\QueueTask;

class QueueSaveMailAttachmentsTask extends QueueTask
{
  public $defaults = [
		'username' => 'username',
		'password' => 'password',
    'mailbox' => '{imap.example.org:993/imap/ssl}INBOX',
    'mailObjectNeedles' => ['test','exemple'],
    'fileNeedles' => ['.*\.xlsx'],
    'folder' => TMP.'MailAttachments/'
	];

  protected $mailbox = null;
  protected $mailsIds = [];
  protected $dir = null;
  protected $tmpDir = null;

  public function add()
  {
    $this->out('Followed data set will be used to run this task:');
		$this->out(var_export($this->defaults, true));
    $this->QueuedJobs->createJob('SaveMailAttachments', ['name' => 'Save mail attachments', 'data' => $this->defaults]);
  }

  public function run(array $data, $id)
  {
    $this->defaults = array_merge($this->defaults, $data);
    $this->_connect();

    $count = $success = 0;
    foreach($this->defaults['mailObjectNeedles'] as $needle)
    {
      $files = $this->_getMailAttachments($needle);
      foreach($files as $key => $file)
      {
        $count++;
        $this->out('moving file: '.$key.' -> '.$file->name);
        $this->out('to: '.$this->dir->path.$file->name);
        debug($file->info());
        if($file->copy($this->dir->path.$file->name))
        {
          $success++;
        }else{
          $this->err('Error on copy file');
        }
      }
      $this->_clean();
    }

    // delete stuff & close connection
    $this->_destroy();

    // inform
    $status = $success.' files were successfully saved on in folder '.$this->dir->path;
    $this->hr();
    $this->info("\n".$status."\n");
    $this->hr();

    // update status
    $this->QueuedJobs->updateAll(['status' => $status ], ['id' => $id]);

    return true;
  }

  protected function _connect()
  {
    $subdir = new \DateTime();
    $this->tmpDir = new Folder(TMP.'attachments/'.date('Y-m-d').'/', true, 0777);
    $this->dir = new Folder($this->defaults['folder'], true, 0777);
    $this->mailbox = new ImapMailbox($this->defaults['mailbox'], $this->defaults['username'], $this->defaults['password'], $this->tmpDir->path);
  }

  protected function _clean()
  {
    foreach($this->mailsIds as $mailsId)
    {
      $this->info('deleteMail: '.$mailsId);
      //$this->mailbox->deleteMail($mailsId);
    }
  }

  protected function _destroy()
  {
    $this->tmpDir->delete();
    //$this->mailbox->expungeDeletedMails();
  }

  protected function _getMailAttachments($needle)
  {
    $this->out('looking for: '.$needle);
    $this->mailsIds = $this->mailbox->searchMailbox('SUBJECT "'.$needle.'"');
    return empty($this->mailsIds)? []: $this->_getAllFiles();
  }

  protected function _getAllFiles()
  {
    foreach($this->mailsIds as $mailsId)
    {
      $this->out('get mail: '.$mailsId);
      $this->mailbox->getMail($mailsId);
    }
    $files = [];
    foreach($this->defaults['fileNeedles'] as $needle )
    {
      $filePaths = $this->tmpDir->find($needle, true);
      $this->out('found '.count($filePaths).' fils for "'.$needle.'":');
      foreach($filePaths as $file)
      {
        debug($this->tmpDir->path.$file);
        $files[] = new File($this->tmpDir->path.$file);
      }
    }
    return $files;
  }
}
