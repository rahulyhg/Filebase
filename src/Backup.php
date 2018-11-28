<?php
namespace Filebase;
use \Filebase\Helper\Scanner;

class Backup
{

    /**
    * $backupLocation
    *
    * Current backup location..
    * $backupLocation
    */
    protected $backupLocation;


    //--------------------------------------------------------------------


    /**
    * $config
    *
    * Stores all the configuration object settings
    * \Filebase\Config
    */
    protected $config;


    //--------------------------------------------------------------------


    /**
    * $database
    *
    * \Filebase\Database
    */
    protected $database;


    //--------------------------------------------------------------------


    /**
    * __construct
    *
    */
    public function __construct($backupLocation = '', Database $database)
    {
        $this->backupLocation = $backupLocation;
        $this->config = $database->getConfig();
        $this->database = $database;

        // Check directory and create it if it doesn't exist
        if (!is_dir($this->backupLocation))
        {
            if (!@mkdir($this->backupLocation, 0777, true))
            {
                throw new \Exception(sprintf('`%s` doesn\'t exist and can\'t be created.', $this->backupLocation));
            }
        }
        else if (!is_writable($this->backupLocation))
        {
            throw new \Exception(sprintf('`%s` is not writable.', $this->backupLocation));
        }
    }


    //--------------------------------------------------------------------


    /**
    * save()
    *
    */
    public function create()
    {
        $backupFile = $this->backupLocation.DIRECTORY_SEPARATOR.time().'.zip';

        if ($results = $this->zip($this->config->dir, $backupFile))
        {
            $basename = basename($backupFile);
            return $basename;
        }

        throw new \Exception('Error backing up database.');
    }


    //--------------------------------------------------------------------


    /**
    * find()
    *
    * Returns an array of all the backups currently available
    *
    */
    public function find()
    {
        $backups = [];
        $ext = 'zip';
        $path = realpath($this->backupLocation).DIRECTORY_SEPARATOR;
        $files = Scanner::fileSearch($path,$ext);
        foreach($files as $file)
        {
            $basename = str_replace('.'.$ext,'',basename($file));
            $backups[$basename] = $this->backupLocation.DIRECTORY_SEPARATOR.$basename.'.'.$ext;
        }

        krsort($backups);

        return $backups;
    }


    //--------------------------------------------------------------------


    /**
    * clean()
    *
    * Clears and deletes all backups (zip files only)
    *
    */
    public function clean()
    {
        $ext = 'zip';
        $path = realpath($this->backupLocation).DIRECTORY_SEPARATOR;
        $files = Scanner::fileSearch($path,$ext);
        return array_map('unlink', $files);
    }


    //--------------------------------------------------------------------


    /**
    * rollback()
    *
    * Rollback database to the last backup available
    *
    */
    public function rollback()
    {
        $backups = $this->find();
        $restore = current($backups);

        $this->database->truncate();

        return $this->extract($restore, $this->config->dir);
    }


    //--------------------------------------------------------------------


    /**
     * extract()
     *
     * @param string $source (zip location)
     * @param string $target (unload files to location)
     * @return bool
     */
    protected function extract($source = '', $target = '')
    {
        if (extension_loaded('zip'))
        {
            if (file_exists($source))
            {
                $zip = new \ZipArchive();
                if ($zip->open($source) === TRUE)
                {
                    $zip->extractTo($target);
                    $zip->close();

                    return true;
                }
            }
        }

        return false;
    }


    //--------------------------------------------------------------------


    /**
    * zip()
    *
    * Prevents the zip from zipping up the storage diretories
    *
    */
    protected function zip($source = '', $target = '')
    {
        if (extension_loaded('zip'))
        {
           if (file_exists($source))
           {
               $zip = new \ZipArchive();
               if ($zip->open($target, \ZIPARCHIVE::CREATE))
               {
                   $source = realpath($source);
                   if (is_dir($source))
                   {
                       $iterator = new \RecursiveDirectoryIterator($source);
                       $iterator->setFlags(\RecursiveDirectoryIterator::SKIP_DOTS);
                       $files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);
                       foreach ($files as $file)
                       {
                           $file = realpath($file);

                           if (preg_match('|'.realpath($this->backupLocation).'|',$file))
                           {
                               continue;
                           }

                           if (is_dir($file))
                           {
                               $zip->addEmptyDir(str_replace($source . DIRECTORY_SEPARATOR, '', $file . DIRECTORY_SEPARATOR));
                           }
                           else if (is_file($file))
                           {
                               $zip->addFromString(str_replace($source . DIRECTORY_SEPARATOR, '', $file), file_get_contents($file));
                           }
                       }

                   }
                   else if (is_file($source))
                   {
                       $zip->addFromString(basename($source), file_get_contents($source));
                   }
               }

               return $zip->close();
           }
       }

       return false;
    }



}
