<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShareLinkRequest;
use App\Services\FileService;
use App\Services\ShareLinkService;
use App\utils\HttpError;
use App\utils\Security;
use App\utils\ExceptionCustom\ShareLinkException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class ShareLinkController extends Controller
{
    public function __construct(
        private ShareLinkService $shareLinkService,
        private FileService $fileService
    ){}

    private function handleShareLinkErrors(callable $action){
        try{
            return $action();
        }catch(ShareLinkException $e){
            abort(400, $e->getMessage());
        }catch(ModelNotFoundException $e){
            abort(404, $e->getMessage());
        }catch(ValidationException $e){
            abort(422, $e->getMessage());
        }catch(Exception $e){
            HttpError::InternalError($e);
        }
    }

    public function createShareLink(string $fileId, ShareLinkRequest $req){
        return $this->handleShareLinkErrors(function() use ($fileId, $req){
            $user = Security::isOwner();
            $file = $this->fileService->getFile($user->id, $fileId);

            return response()->json([
                "shareLink" => $this->shareLinkService->createShareLink($file, $req->validated())
            ]);
        });
    }

    public function getShareLinkData(string $token){
        return $this->handleShareLinkErrors(function() use ($token){
            $user = Security::isOwner();
            $data = $this->shareLinkService->getShareLinkData($user->id, $token);

            return response()->json(["shareLink" => $data]);
        });
    }

    public function getShareLink(string $token, Request $req){
        return $this->handleShareLinkErrors(function() use ($token, $req){
            $url = $this->shareLinkService->getShareLink($token, $req->input("password"));

            return response()->json(["url" => $url]);
        });
    }

    public function getShareLinkConfig(string $token){
        return $this->handleShareLinkErrors(function() use ($token){
            return response()->json([
                "config" => $this->shareLinkService->getShareLinkConfig($token)
            ]);
        });
    }
}
