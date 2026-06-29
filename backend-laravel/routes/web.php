<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

Route::get('/', function () {
    return redirect('/frontend/html/login.html');
});

Route::get('/login.html', function () {
    return redirect('/frontend/html/login.html');
});

Route::get('/frontend/html/{any}', function ($any) {
    $path = public_path('frontend/html/' . $any);

    if (!File::exists($path)) {
        abort(404);
    }

    $mime = match(pathinfo($path, PATHINFO_EXTENSION)) {
        'js'   => 'application/javascript',
        'css'  => 'text/css',
        'html' => 'text/html',
        'png'  => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        default => 'text/plain',
    };

    return response(File::get($path), 200)->header('Content-Type', $mime);
})->where('any', '.*');