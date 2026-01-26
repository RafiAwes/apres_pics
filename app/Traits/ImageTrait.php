<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Termwind\Components\Raw;

trait ImageTrait
{
    public function uploadAvatar(Request $request, $inputName, $path)
    {
        if($request->hasfile($inputName)) {
            $file = $request->file($inputName);
            $fileName = time().'_'.$file->getClientOriginalName();
            $destinationPath = public_path($path);
            
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }
            
            $file->move($destinationPath, $fileName);
            return $path.'/'.$fileName;
        }
        return null;
        
    }

    public function deleteImage($imagePath)
    {
        $fullPath = public_path($imagePath);
        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }
    
}