<?php

namespace Modules\Shared\Printing\Data;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A rendered PDF, returned to the client as the binary body (`application/pdf`, shown inline) so a print
 * service can send it straight to a printer.
 */
final readonly class PdfDocument implements Responsable
{
    public function __construct(
        public string $content,
        public string $filename,
        public int $status = 200,
    ) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return new Response($this->content, $this->status, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->filename.'"',
            'Content-Length' => (string) strlen($this->content),
        ]);
    }
}
