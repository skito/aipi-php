<?php

namespace AIpi;

class ModelBase
{
    public static $models = [];
    
    protected static function RegisterModel($model)
    {
        if ($model instanceof IModel)
        {
            $uniqueName = self::GetUniqueName($model->GetName());
            self::$models[$uniqueName] = $model;
        }
    }

    private static function GetUniqueName($name)
    {
        return str_replace(['-', '_', ' ', "\t"], '', strtolower($name));
    }

    private static function IncludeModelsByVendor($vendor)
    {
        $vendorUniqueName = self::GetUniqueName($vendor);
        $baseDir = dirname(__FILE__).'/Models/';
        $files = scandir($baseDir);
        
        foreach ($files as $file)
        {
            if (in_array($file, ['..', '.']))
                continue;
            
            $fileInfo = pathinfo($file);
            if ($fileInfo['extension'] != 'php') 
                continue;

            $filename = $fileInfo['filename'];
            $fileVendor = explode('-', $filename)[0];
            if (self::GetUniqueName($fileVendor) == $vendorUniqueName)
            {
                require_once $baseDir.$file;
            }
        }
    }

    public static function Get($name)
    {
        $uniqueName = self::GetUniqueName($name);
        if (isset(self::$models[$uniqueName]))
            return self::$models[$uniqueName];

        // Locate models
        $vendor = explode('-', $name)[0];
        self::IncludeModelsByVendor($vendor);

        if (isset(self::$models[$uniqueName]))
            return self::$models[$uniqueName];        

        return null;
    }
}
