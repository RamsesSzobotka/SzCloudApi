<?php

namespace App\Services;
use App\Models\ShareLink;
use App\Models\File;
use App\utils\MinIOHelper;
use App\utils\ExceptionCustom\ShareLinkException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ShareLinkService {

    public function createShareLink(File $file, array $config): array {
        $token = bin2hex(random_bytes(32));

        $config["file_id"] = $file->id;
        $config["token_hash"] = hash('sha256',$token);

        if (!empty($config["password"])) {
            $config["password_hash"] = Hash::make($config["password"]);
        }
        unset($config["password"]);

        ShareLink::create($config);

        return [
            "token" => $token,
            "url" => config('app.frontend_url') . '/share/' . $token,
        ];
    }

    public function getShareLinkData(string $userId, string $token): array {
        $link = ShareLink::where("token_hash", hash('sha256',$token))
            ->whereHas("file", fn($q) => $q->where("user_id", $userId))
            ->with("file:id,name")
            ->first();

        if (!$link) {
            throw new ModelNotFoundException("Link compartido no encontrado");
        }

        return [
            "token" => $token,
            "url" => config('app.frontend_url') . '/share/' . $token,
            "expires_at" => $link->expires_at?->toIso8601String(),
            "max_downloads" => $link->max_downloads,
            "download_count" => $link->download_count,
            "requires_password" => $link->password_hash !== null,
            "is_valid" => $link->isValid(),
            "file_name" => $link->file->name ?? null,
            "created_at" => $link->created_at?->toIso8601String(),
        ];
    }

    public function getShareLinkConfig(string $token): array {
        $link = ShareLink::where("token_hash", hash('sha256',$token))
            ->with("file:id,name")
            ->first();

        if (!$link) {
            throw new ModelNotFoundException("Link compartido no encontrado");
        }

        return [
            "requires_password" => $link->password_hash !== null,
            "expires_at" => $link->expires_at?->toIso8601String(),
            "max_downloads" => $link->max_downloads,
            "download_count" => $link->download_count,
            "is_valid" => $link->isValid(),
            "file_name" => $link->file->name ?? null,
        ];
    }

    public function getShareLink(string $token, ?string $password = null): string {
        $file = DB::transaction(function () use ($token, $password) {
            $link = ShareLink::where("token_hash", hash('sha256',$token))->lockForUpdate()->first();

            if (!$link) {
                throw new ModelNotFoundException("Link compartido no encontrado");
            }

            if (!$link->isValid()) {
                throw new ShareLinkException("El link compartido esta expirado o no es valido");
            }

            if ($link->password_hash !== null && !Hash::check($password ?? "", $link->password_hash)) {
                throw new ShareLinkException("Contraseña invalida");
            }

            $link->increment("download_count");

            return $link->file;
        });

        return MinIOHelper::urlDownloadFile($file);
    }
}
