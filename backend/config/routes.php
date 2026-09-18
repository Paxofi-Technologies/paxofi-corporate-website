<?php declare(strict_types=1);
return [
 ['GET','/api/v1/health','public','health'],
 ['GET','/api/v1/readiness','public','readiness'],
 ['GET','/api/v1/content','public','published content'],
 ['GET','/api/v1/navigation','public','navigation'],
 ['GET','/api/v1/products','public','published products'],
 ['GET','/api/v1/services','public','published services'],
 ['GET','/api/v1/careers','public','published careers'],
 ['POST','/api/v1/forms/{form_key}/submit','public+rate-limit','form submission'],
 ['GET','/api/v1/admin/content','admin','content management'],
 ['GET','/api/v1/admin/media','admin','media management'],
 ['GET','/api/v1/admin/audit','admin','audit log'],
];