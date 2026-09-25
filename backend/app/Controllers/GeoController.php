<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\GeoService;

final class GeoController
{
    public static function countries(Request $req, array $params = []): void
    {
        Response::json(GeoService::countries());
    }

    public static function states(Request $req, array $params = []): void
    {
        Response::json(GeoService::states($req->qInt('country_id')));
    }

    public static function cities(Request $req, array $params = []): void
    {
        Response::json(GeoService::cities($req->qInt('state_id')));
    }
}
