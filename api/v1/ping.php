<?php
// Does nothing except prove the chain works: the /api/v1/ folder is reachable,
// subdomain.php lets it through without redirecting, config/api.php loads, the
// database connects, and the deploy actually uploaded the file. Open it in a
// browser after deploying — anything other than {"ok":true} means the problem
// is here, not in the endpoint you were about to write.
require __DIR__ . '/../../config/api.php';

api_json(['ok' => true]);
