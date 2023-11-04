<?php

namespace App\Controllers;

use App\Models\File;
use App\Services\Csrf;
use App\Services\Message;
use App\Services\Security;
use App\Services\Upload;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Comment;

class FileController extends AbstractController
{
    private $maxFileSize = 20 * 1024 * 1024;
    private $maxTotalFileSize = 200 * 1024 * 1024;
 
    public function upload(): Response
    {
        $messageService = new Message();

        if (!(new Csrf())->check($this->request->get('csrf'))) {
            $this->logger->log("Insalid CSRF token");
            return $this->error('Invalid CSRF token', 400);
        }

        $file = $this->request->files->get('file');

        if (!$file) {
            $this->logger->log("Unable to upload file : No file");
            $messageService->addMessage('No file uploaded');
            $response = new RedirectResponse('/dashboard');
            return $response->send();
        }

        // Get the file's extension
        $fileExtension = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);

        // Convert the extension to lowercase and check if it contains "php"
        if (str_contains(strtolower($fileExtension), 'php')) {
            $this->logger->log("Unable to upload file : Files with $fileExtension extension are not allowed");
            $messageService->addMessage("Files with $fileExtension extension are not allowed");
            $response = new RedirectResponse('/dashboard');
            return $response->send();
        }
// MAIKL gestion de quotas
        $fileSize = $file->getSize();
        if ($fileSize > $this->maxFileSize) {
            $this->logger->log("File size exceeds the 20MB limit");
            return $this->error('File size exceeds 20MB', 400);
        }
    
        $fileModel = new File();
        $usedSize = $fileModel->getUsedSize((int)$_SESSION['user']['id']);
        $totalFileSize = $usedSize + $fileSize;
       
       
        if ($totalFileSize > $this->maxTotalFileSize) {
            $this->logger->log("Total file size exceeds the 200 MB limit");
            return $this->error("Total file size exceeds 200 MB", 400);
        }
        
        $filename = $file->getClientOriginalName();
    

        $upload = new Upload();
        $path = $upload->upload($file);

        if (!$path) {
            $this->logger->log("Unable to upload file : File error");
            $messageService->addMessage('An error occurred during the upload.');
            $response = new RedirectResponse('/dashboard');
            return $response->send();
        }


        $fileModel = new File();
        $result = $fileModel->create(
            $path,
            $filename,
            $this->request->request->get('description'),
            (int)$_SESSION['user']['id'],
            null,
            null,
            false,
            false,
            0,
            $fileSize,
        );

        if (!$result) {
            $this->logger->log("Unable to upload file : Database error");
            return $this->error('An error occurred', 500);
        }

        $response = new RedirectResponse('/dashboard');
        return $response->send();
    }

    public function downloadUser($id): Response
    {
        $fileModel = new File();
        $file = $fileModel->get($id);

        if (!$file || !file_exists($file['path']) || $file['user_id'] !== $_SESSION['user']['id']) {
            $response = new Response('File not found', 404);
            return $response->send();
        }

        $upload = new Upload();
        return $upload->download($file['path'], $file['filename']);
    }

    public function delete($id): Response
    {
        if (!(new Csrf())->check($this->request->get('csrf'))) {
            return $this->error('Invalid CSRF token', 400);
        }

        $fileModel = new File();
        $file = $fileModel->get($id);

        if (!$file || !file_exists($file['path']) || $file['user_id'] !== $_SESSION['user']['id']) {
            $this->logger->log("Unable to delete file with id $id : File not found");
            return $this->error('File not found');
        }

        $upload = new Upload();
        if (!$upload->delete($file['path'])) {
            $this->logger->log("Unable to delete file with id $file[id] on disk");
            return $this->error('An error occurred', 500);
        }

        $commentModel = new Comment();
        if (!$commentModel->deleteByFile($id)) {
            $this->logger->log("Unable to delete comments for file with id $file[id] in database");
            return $this->error('An error occurred', 500);
        }

        if (!$fileModel->delete($id)) {
            $this->logger->log("Unable to delete file with id $file[id] in database");
            return $this->error('An error occurred', 500);
        }

        $response = new RedirectResponse('/dashboard');
        return $response->send();

    }

    public function makePublic($id): Response
    {
        if (!(new Csrf())->check($this->request->get('csrf'))) {
            return $this->error('Invalid CSRF token', 400);
        }

        $fileModel = new File();
        $file = $fileModel->get($id);
        if (!$file || !file_exists($file['path']) || $file['user_id'] !== $_SESSION['user']['id']) {
            return $this->error('File not found');
        }

        $isPublic = $this->request->get('isPublic') === 'on';
        $hasPassword = $isPublic && $this->request->get('hasPassword') === 'on';
        $hashedPassword = $hasPassword ? password_hash($this->request->get('password'), PASSWORD_DEFAULT) : null;

        $token = null;
        if ($isPublic) {
            $token = !$file['isPublic'] ? bin2hex(random_bytes(16)) : $file['token'];
        }

        // TODO Check if the token already exist

        $fileModel = new File();

        if (!$fileModel->makePublic((int)$id, $isPublic, $token, $hasPassword, $hashedPassword)) {
            $this->logger->log("Unable to make file with id $id public");
            return $this->error('An error occurred', 500);
        }

        $response = new RedirectResponse('/file/' . $id);
        return $response->send();
    }

    public function downloadPublic($token): Response
    {
        $fileModel = new File();
        $file = $fileModel->getByToken($token);

        if (!$file || !$file['isPublic']) {
            return $this->error('File not found');
        }

        $commentModel = new Comment();
        $security = new Security();

        $response = new Response(
            $this->render('Home/file', [
                'file' => $file,
                'messages' => Message::getMessages(),
                'csrf' => (new Csrf())->generate(),
                'comments' => $commentModel->getByFile($file['id']),
                "isConnected" => $security->isConnected(),
            ])
        );
        return $response->send();
    }

    public function downloadPublicProcess($token): Response
    {
        $fileModel = new File();
        $file = $fileModel->getByToken($token);

        if (!$file || !$file['isPublic']) {
            $this->error('File not found');
        }

        if ($file['hasPassword'] && !password_verify($this->request->get('password'), $file['password'])) {
            // TODO Redirect with error
            Message::addMessage('Invalid password');
            return (new RedirectResponse('/dl/' . $token))->send();
        }

        $fileModel->incrementDownloadCount((int)$file['id']);

        $upload = new Upload();
        return $upload->download($file['path'], $file['filename']);
    }

    public function updateDescription(): Response
    {
        if (!(new Csrf())->check($this->request->get('csrf'))) {
            return $this->error('Invalid CSRF token', 400);
        }

        $newDescription = $this->request->get('new_description');
        $fileId = $this->request->get('file_id');

        if (empty($newDescription) || empty($fileId)) {
            return $this->error('Fields cannot be empty', 400);
        }

        $fileModel = new File();
        $file = $fileModel->get($fileId);

        if (!$file || $file['user_id'] !== $_SESSION['user']['id']) {
            return $this->error('File not found or you do not have permission', 404);
        }

        if (!$fileModel->updateDescription($fileId, $newDescription, $file['user_id'])) {
            $this->logger->log("Unable to update description for file with id $fileId");
            return $this->error('An error occurred', 500);
        }

        $response = new RedirectResponse('/file/' . $fileId);
        return $response->send();
    }



}




