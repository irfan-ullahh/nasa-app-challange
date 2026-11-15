<?php

use App\Livewire\Analytics;
use App\Livewire\Dashboard;
use App\Livewire\DataLayerDetail;
use App\Livewire\InteractiveMap;
use App\Livewire\NisarFeed;
use App\Livewire\Team;
use App\Livewire\UploadData;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->name('dashboard');
Route::get('/analytics', Analytics::class)->name('analytics.index');
Route::get('/map', InteractiveMap::class)->name('map.index');
Route::get('/nisar-feed', NisarFeed::class)->name('nisar.index');
Route::get('/upload-data', UploadData::class)->name('upload.index');
Route::get('/layers/{layer}', DataLayerDetail::class)->name('layers.show');
Route::get('/team', Team::class)->name('team');
