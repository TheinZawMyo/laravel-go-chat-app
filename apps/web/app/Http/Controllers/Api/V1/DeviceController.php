<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserDeviceResource;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DeviceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', UserDevice::class);

        $devices = $request->user()
            ->devices()
            ->latest('last_active_at')
            ->get();

        return UserDeviceResource::collection($devices);
    }

    public function destroy(UserDevice $device): Response
    {
        $this->authorize('delete', $device);

        if (! $device->isRevoked()) {
            $device->forceFill(['revoked_at' => now()])->save();
        }

        return response()->noContent();
    }
}
