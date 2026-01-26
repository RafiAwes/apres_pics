<?php

namespace App\Traits;

use \Illuminate\Http\JsonResponse;

trait ApiResponseTraits
{
    public function successResponse($data = null, string $message = 'Success', int $code): JsonResponse
    {
        return response()->json([
            'success'=> true,
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public function errorResponse(string $message = 'Error', int $code = 400, $errors = null): JsonResponse
    {
        
       $response = [
        'success'=> false,
        'status'=> 'error',
        'message'=> $message,
       ];

       if(!empty($errors)){
        $response['errors'] = $errors;
       }
        
       return response()->json($response, $code);
    }
}