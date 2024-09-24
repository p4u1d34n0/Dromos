<?php

namespace App\Controllers;

class ViewController extends Controller
{
    public function index($id)
    {
        echo 'My homepage is working! The ID is: ' . $id;
    }

    public function merge($id, $user_id)
    {
        return 'My homepage is working! The ID is: ' . $id . ' and the user ID is: ' . $user_id;
    }
}