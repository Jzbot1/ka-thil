<?php
/**
 * Hidden Index - Returns 404
 */
http_response_code(404);
echo "<h1>404 Not Found</h1>";
echo "The page that you have requested could not be found.";
exit();
?>
