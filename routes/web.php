<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('trigger-event', function () {
    $comment = App\Models\ChatMessage::first();
    broadcast(new App\Events\ChatCreated($comment));
    return 'Event has been sent!';
});
