<?php

namespace App;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *   title="Marge PDF`s",
 *   version="1.0.0",
 *   description="API para unir boletos (PDFs) em um único arquivo"
 * )
 *
 * @OA\Server(
 *   url=L5_SWAGGER_CONST_HOST,
 *   description="Servidor local"
 * )
 *
 * @OA\Tag(
 *   name="PDF",
 *   description="Operações relacionadas a PDFs"
 * )
 */
class OpenApi
{
    // Classe marcador apenas para hospedar anotações globais do OpenAPI
}