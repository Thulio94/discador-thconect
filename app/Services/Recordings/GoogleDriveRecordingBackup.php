<?php

namespace App\Services\Recordings;

use App\Models\Call;
use App\Models\Company;
use App\Models\GoogleDriveBackupSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class GoogleDriveRecordingBackup
{
    private const DRIVE_SCOPE = 'https://www.googleapis.com/auth/drive.file';

    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    public function authorizationUrl(string $redirectUrl, string $state): string
    {
        $setting = GoogleDriveBackupSetting::current();
        $this->assertClientConfigured($setting);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $setting->client_id,
            'redirect_uri' => $redirectUrl,
            'response_type' => 'code',
            'scope' => self::DRIVE_SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function exchangeAuthorizationCode(string $code, string $redirectUrl): void
    {
        $setting = GoogleDriveBackupSetting::current();
        $this->assertClientConfigured($setting);

        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $setting->client_id,
            'client_secret' => $setting->client_secret,
            'redirect_uri' => $redirectUrl,
            'grant_type' => 'authorization_code',
        ]);
        if ($response->failed() || blank($response->json('refresh_token'))) {
            throw new RuntimeException('O Google Drive não forneceu uma credencial de acesso permanente. Autorize novamente.');
        }

        $setting->update([
            'refresh_token' => $response->json('refresh_token'),
            'root_folder_id' => null,
            'enabled' => true,
            'last_error' => null,
        ]);
    }

    public function backup(Call $call): string
    {
        $setting = GoogleDriveBackupSetting::current();
        if (! $setting->enabled || ! $setting->isConnected()) {
            throw new RuntimeException('O backup no Google Drive não está autenticado e ativo.');
        }

        $sourcePath = Storage::disk('recordings')->path($call->recording_path);
        if (! is_file($sourcePath)) {
            throw new RuntimeException('A gravação local não está disponível para backup.');
        }

        $client = $this->authorizedClient($setting);
        $rootId = $setting->root_folder_id ?: $this->folder($client, $setting->root_folder_name, 'root');
        if ($rootId !== $setting->root_folder_id) {
            $setting->update(['root_folder_id' => $rootId]);
        }

        $companyFolder = $this->folder($client, $this->companyFolderName($call->company), $rootId);
        $dateFolder = $this->folder($client, ($call->started_at ?? now())->format('Y-m-d'), $companyFolder);
        $filename = sprintf('%s_%s.%s', $call->protocol ?: $call->id, Str::slug($call->phone ?: 'chamada'), $call->recording_format ?: pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'wav');

        $session = $client
            ->withHeaders([
                'X-Upload-Content-Type' => $this->mimeType($sourcePath),
                'X-Upload-Content-Length' => (string) filesize($sourcePath),
            ])
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', [
                'name' => $filename,
                'parents' => [$dateFolder],
                'description' => sprintf('Empresa %s | protocolo %s | chamada %d', $call->company_id, $call->protocol, $call->id),
            ]);
        $location = $session->header('Location');
        if ($session->failed() || blank($location)) {
            throw new RuntimeException('Não foi possível iniciar o envio da gravação ao Google Drive.');
        }

        $stream = fopen($sourcePath, 'rb');
        try {
            $upload = $client->withBody($stream, $this->mimeType($sourcePath))->put($location);
        } finally {
            fclose($stream);
        }
        if ($upload->failed() || blank($upload->json('id'))) {
            throw new RuntimeException('O Google Drive não confirmou o envio da gravação.');
        }

        return (string) $upload->json('id');
    }

    public function download(string $fileId): StreamInterface
    {
        $client = $this->authorizedClient(GoogleDriveBackupSetting::current());
        $response = $client->withOptions(['stream' => true])->get("https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media");
        if ($response->failed()) {
            throw new RuntimeException('A gravação não está disponível no Google Drive.');
        }

        return $response->toPsrResponse()->getBody();
    }

    public function delete(string $fileId): void
    {
        $response = $this->authorizedClient(GoogleDriveBackupSetting::current())
            ->delete("https://www.googleapis.com/drive/v3/files/{$fileId}");
        if ($response->failed() && $response->status() !== 404) {
            throw new RuntimeException('Não foi possível remover a gravação expirada do Google Drive.');
        }
    }

    private function authorizedClient(GoogleDriveBackupSetting $setting): PendingRequest
    {
        $this->assertClientConfigured($setting);
        if (blank($setting->refresh_token)) {
            throw new RuntimeException('Conecte uma conta Google antes de executar o backup.');
        }
        $token = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $setting->client_id,
            'client_secret' => $setting->client_secret,
            'refresh_token' => $setting->refresh_token,
            'grant_type' => 'refresh_token',
        ]);
        if ($token->failed() || blank($token->json('access_token'))) {
            throw new RuntimeException('A autenticação do Google Drive expirou ou foi revogada. Conecte novamente.');
        }

        return Http::acceptJson()->withToken($token->json('access_token'))->timeout(120);
    }

    private function folder(PendingRequest $client, string $name, string $parentId): string
    {
        $query = sprintf("name = '%s' and mimeType = '%s' and '%s' in parents and trashed = false", str_replace("'", "\\'", $name), self::FOLDER_MIME, $parentId);
        $existing = $client->get('https://www.googleapis.com/drive/v3/files', ['q' => $query, 'fields' => 'files(id)', 'spaces' => 'drive']);
        if ($existing->successful() && filled($existing->json('files.0.id'))) {
            return (string) $existing->json('files.0.id');
        }
        if ($existing->failed()) {
            throw new RuntimeException('Não foi possível consultar a estrutura de pastas no Google Drive.');
        }
        $created = $client->post('https://www.googleapis.com/drive/v3/files', [
            'name' => $name,
            'mimeType' => self::FOLDER_MIME,
            'parents' => [$parentId],
        ]);
        if ($created->failed() || blank($created->json('id'))) {
            throw new RuntimeException('Não foi possível preparar a estrutura de pastas no Google Drive.');
        }

        return (string) $created->json('id');
    }

    private function companyFolderName(?Company $company): string
    {
        return sprintf('%06d - %s', $company?->id ?? 0, Str::limit(Str::slug($company?->name ?: 'empresa'), 80, ''));
    }

    private function mimeType(string $path): string
    {
        return mime_content_type($path) ?: 'application/octet-stream';
    }

    private function assertClientConfigured(GoogleDriveBackupSetting $setting): void
    {
        if (blank($setting->client_id) || blank($setting->client_secret)) {
            throw new RuntimeException('Informe o Client ID e o Client Secret do Google antes de conectar.');
        }
    }
}
