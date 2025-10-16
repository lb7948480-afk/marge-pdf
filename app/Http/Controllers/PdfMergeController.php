<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use iio\libmergepdf\Merger;
use iio\libmergepdf\Driver\Fpdi2Driver;
use iio\libmergepdf\Driver\TcpdiDriver;
use OpenApi\Annotations as OA;
use Throwable;

class PdfMergeController extends Controller
{
    /**
     * Unir múltiplos PDFs a partir de URLs
     *
     * @OA\Post(
     *   path="/api/merge-pdfs",
     *   summary="Une PDFs vindos de URLs em um único arquivo",
     *   tags={"PDF"},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       type="object",
     *       required={"urls"},
     *       @OA\Property(
     *         property="urls",
     *         type="array",
     *         description="Lista de URLs de PDFs",
     *         @OA\Items(type="string", format="uri")
     *       ),
     *       @OA\Property(
     *         property="filename",
     *         type="string",
     *         description="Nome do arquivo PDF resultante",
     *         example="boletos-unidos.pdf"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="URL do PDF mesclado",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="url", type="string", format="uri"),
     *       @OA\Property(property="filename", type="string")
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Erro de validação ou download",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string"),
     *       @OA\Property(property="error", type="string")
     *     )
     *   )
     * )
     */
    public function mergeFromUrls(Request $request)
    {
        $data = $request->validate([
            'urls' => 'required|array|min:1',
            'urls.*' => 'string|url',
            'filename' => 'sometimes|string',
        ]);

        $urls = $data['urls'];
        $filename = $data['filename'] ?? 'merged.pdf';

        $tempDir = 'tmp/pdf-merge-' . Str::uuid();
        Storage::makeDirectory($tempDir);

        $paths = [];
        try {
            foreach ($urls as $i => $url) {
                $response = Http::timeout(30)
                    ->withHeaders(['Accept' => 'application/pdf,*/*'])
                    ->get($url);

                if (!$response->ok()) {
                    throw new \RuntimeException("Falha ao baixar PDF: {$url} (HTTP {$response->status()})");
                }

                $contentType = $response->header('Content-Type');
                $body = $response->body();

                if ($contentType && !str_contains(strtolower($contentType), 'pdf')) {
                    if (strpos($body, '%PDF') !== 0) {
                        throw new \RuntimeException("Conteúdo não é um PDF válido: {$url}");
                    }
                }

                $filePath = $tempDir . '/part-' . ($i + 1) . '.pdf';
                Storage::put($filePath, $body);
                $paths[] = Storage::path($filePath);
            }

            $mergeWith = function ($driver) use ($paths) {
                $merger = new Merger($driver);
                foreach ($paths as $p) {
                    $merger->addFile($p);
                }
                return $merger->merge();
            };

            try {
                // Tenta com FPDI (rápido, comum)
                $merged = $mergeWith(new Fpdi2Driver());
            } catch (\Throwable $fpdiError) {
                // Fallback: TCPDI lida melhor com PDFs v1.5+ com streams comprimidos
                $merged = $mergeWith(new TcpdiDriver());
            }

            // Salva o PDF mesclado no disco público e retorna a URL
            $safeBase = Str::slug(pathinfo($filename, PATHINFO_FILENAME)) ?: 'merged';
            $safeName = $safeBase . '.pdf';
            $publicDir = 'merged';
            $publicPath = $publicDir . '/' . now()->format('YmdHis') . '-' . Str::uuid() . '-' . $safeName;
            Storage::disk('public')->put($publicPath, $merged);
            $fileUrl = asset('storage/' . ltrim($publicPath, '/'));

            foreach ($paths as $p) {
                @unlink($p);
            }
            Storage::deleteDirectory($tempDir);

            return response()->json([
                'url' => $fileUrl,
                'filename' => $safeName,
            ], 200);
        } catch (Throwable $e) {
            foreach ($paths as $p) {
                @unlink($p);
            }
            Storage::deleteDirectory($tempDir);

            return response()->json([
                'message' => 'Erro ao unir PDFs',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}