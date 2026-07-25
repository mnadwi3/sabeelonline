<?php
// Deny direct listing of this folder
http_response_code(403);
exit('Forbidden');
