<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{File, Log, Storage};

trait ImageTrait
{
    public function uploadAvatar(Request $request, $inputName, $path)
    {
        if ($request->hasFile($inputName)) {
            $file = $request->file($inputName);

            if (! $file->isValid()) {
                throw new \Exception('Avatar upload failed');
            }

            $fileName = time().'_'.$file->getClientOriginalName();
            $storedPath = Storage::disk('public')->putFileAs($path, $file, $fileName);

            if (! $storedPath) {
                throw new \Exception('Avatar could not be saved to storage path: '.$path);
            }

            return 'storage/'.$storedPath;
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

                // Store in public disk (storage/app/public)
                $storedPath = Storage::disk('public')->putFileAs($path, $file, $fileName);

                if (!$storedPath) {
                    throw new \Exception('File could not be saved to storage path: '.$path);
                }

                // Return public URL path
                return 'storage/'.$storedPath;
            } catch (\Exception $e) {
                Log::error('Image upload failed: '.$e->getMessage());
                throw $e;
            }
        }
        
        throw new \Exception('No file provided in request field: '.$inputName);
    }

    public function deleteImage($imagePath)
    {
        if (! $imagePath) {
            return;
        }

        $relativePath = $imagePath;

        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            $parsedPath = parse_url($imagePath, PHP_URL_PATH);
            $relativePath = ltrim((string) $parsedPath, '/');
        } else {
            $relativePath = ltrim($imagePath, '/');
        }

        if (str_starts_with($relativePath, 'storage/')) {
            $storageRelativePath = substr($relativePath, strlen('storage/'));
            if (Storage::disk('public')->exists($storageRelativePath)) {
                Storage::disk('public')->delete($storageRelativePath);
                return;
            }
        }

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
            return;
        }

        $fullPath = public_path($relativePath);
        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }
    
}