<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PhilippineLocationController extends Controller
{
    private const API = 'https://psgc.gitlab.io/api';

    public function cities(string $provinceCode): JsonResponse
    {
        $path = $provinceCode === '130000000'
            ? '/regions/130000000/cities-municipalities/'
            : "/provinces/{$provinceCode}/cities-municipalities/";

        return $this->locations("cities:{$provinceCode}", $path);
    }

    public function barangays(string $cityCode): JsonResponse
    {
        return $this->locations("barangays:{$cityCode}", "/cities-municipalities/{$cityCode}/barangays/");
    }

    private function locations(string $cacheKey, string $path): JsonResponse
    {
        try {
            $locations = Cache::remember('psgc:'.$cacheKey, now()->addDays(30), fn (): array => Http::acceptJson()
                ->timeout(12)
                ->retry(2, 250)
                ->get(self::API.$path)
                ->throw()
                ->json());

            return response()->json($locations);
        } catch (ConnectionException|RequestException) {
            return response()->json([
                'message' => 'Philippine location data is temporarily unavailable. Please try again.',
            ], 503);
        }
    }
}
