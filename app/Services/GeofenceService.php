<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class GeofenceService
{
    /**
     * Check if a point (lat, lng) is inside a polygon
     *
     * @param float $latitude
     * @param float $longitude
     * @param array $polygon Array of arrays containing [lng, lat]
     * @return bool
     */
    public static function isInsidePolygon(float $latitude, float $longitude, array $polygon): bool
    {
        $inside = false;
        $numPoints = count($polygon);
        
        for ($i = 0, $j = $numPoints - 1; $i < $numPoints; $j = $i++) {
            $xi = $polygon[$i][0]; // longitude
            $yi = $polygon[$i][1]; // latitude
            $xj = $polygon[$j][0]; // longitude
            $yj = $polygon[$j][1]; // latitude
            
            $intersect = (($yi > $latitude) != ($yj > $latitude))
                && ($longitude < ($xj - $xi) * ($latitude - $yi) / ($yj - $yi) + $xi);
                
            if ($intersect) {
                $inside = !$inside;
            }
        }
        
        return $inside;
    }

    /**
     * Get parsed Barangay Alijis GeoJSON boundary coordinates (cached)
     */
    public static function getAlijisBoundaryData(): ?array
    {
        $geojsonPath = public_path('geojson/alijis_boundary.geojson');
        if (!file_exists($geojsonPath)) {
            return null;
        }

        $fileMtime = filemtime($geojsonPath);

        try {
            return Cache::rememberForever('alijis_boundary_data_' . $fileMtime, function () use ($geojsonPath) {
                $content = file_get_contents($geojsonPath);
                return json_decode($content, true);
            });
        } catch (\Throwable $e) {
            $content = file_get_contents($geojsonPath);
            return json_decode($content, true);
        }
    }

    /**
     * Check if a point is inside the Barangay Alijis boundary
     */
    public static function isInsideAlijis(float $latitude, float $longitude): bool
    {
        // 1. Check Alijis service area bounding box [10.6050, 122.9200] to [10.6750, 122.9850]
        if ($latitude >= 10.6050 && $latitude <= 10.6750 && $longitude >= 122.9200 && $longitude <= 122.9850) {
            return true;
        }

        // 2. Fallback to exact GeoJSON polygon
        $geojson = self::getAlijisBoundaryData();
        if (!$geojson || !isset($geojson['type'])) {
            return false;
        }

        if ($geojson['type'] === 'Polygon') {
            $polygon = $geojson['coordinates'][0];
            return self::isInsidePolygon($latitude, $longitude, $polygon);
        } elseif ($geojson['type'] === 'MultiPolygon') {
            foreach ($geojson['coordinates'] as $poly) {
                if (self::isInsidePolygon($latitude, $longitude, $poly[0])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Assert that coordinates are inside Alijis boundary, throwing ValidationException if not.
     *
     * @throws ValidationException
     */
    public static function isInsideAlijisOrFail(float $latitude, float $longitude, ?string $message = null): void
    {
        if (!self::isInsideAlijis($latitude, $longitude)) {
            $msg = $message ?? 'The specified location is outside the Barangay Alijis service area boundary.';
            throw ValidationException::withMessages([
                'location' => [$msg],
            ]);
        }
    }

    /**
     * Alias for isInsideAlijisOrFail for standardized API calls.
     *
     * @throws ValidationException
     */
    public static function validatePointOrFail(float $latitude, float $longitude, ?string $message = null): void
    {
        self::isInsideAlijisOrFail($latitude, $longitude, $message);
    }
}

