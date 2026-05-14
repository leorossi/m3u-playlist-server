<?php

namespace App\Service;

use App\Entity\Playlist;
use App\Entity\RequestLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class RequestLogService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function log(Request $request, Playlist $playlist): void
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = count($values) === 1 ? $values[0] : $values;
        }

        $log = new RequestLog();
        $log->setPlaylist($playlist);
        $log->setIpAddress($request->getClientIp() ?? 'unknown');
        $log->setUserAgent($request->headers->get('User-Agent'));
        $log->setHeaders($headers);

        $this->em->persist($log);
        $this->em->flush();
    }
}
