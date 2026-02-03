<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Termwind\Components\Raw;

trait ImageTrait
{
    public function uploadAvatar(Request $request, $inputName, $path)
    {
        if($request->hasFile($inputName)) {
            $file = $request->file($inputName);
            $fileName = time().'_'.$file->getClientOriginalName();
            $destinationPath = public_path($path);
            
            // Use the filesystem to store the file
            $file->storeAs($path, $fileName, 'public');
            
            return $path.'/'.$fileName;
        }
        return null;
    }

    public function uploadImage(Request $request, $inputName, $path)
    {
        if($request->hasFile($inputName)) {
            try {
                $file = $request->file($inputName);
                
                // Validate file
                if (!$file->isValid()) {
                    throw new \Exception('File upload failed');
                }
                
                $fileName = time().'_'.$file->getClientOriginalName();
                $destinationPath = public_path($path);
                
                // Create directory if it doesn't exist
                if (!File::exists($destinationPath)) {
                    File::makeDirectory($destinationPath, 0755, true);
                }
                
                // Move file directly to public path
                $file->move($destinationPath, $fileName);
                
                $savedPath = $path.'/'.$fileName;
                
                // Verify file was saved
                if (!File::exists(public_path($savedPath))) {
                    throw new \Exception('File could not be saved to '.$destinationPath);
                }
                
                return $savedPath;
            } catch (\Exception $e) {
                \Log::error('Image upload failed: '.$e->getMessage());
                throw $e;
            }
        }
        
        throw new \Exception('No file provided in request field: '.$inputName);
    }

    public function deleteImage($imagePath)
    {
        $fullPath = public_path($imagePath);
        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }
    
}