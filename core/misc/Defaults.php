<?php

namespace core\misc;

class Defaults
{
	
	// Errors
	const ERROR_401 = "401! Unauthorized Access.";
	const ERROR_403 = "403! Forbidden Error.";
	const ERROR_404 = "404! Page not found.";
	const ERROR_500 = "500! Internal Server Error.";
	const ERROR_502 = "502! Bad Gateway.";

	// Randomizer Types
	const RAND_ALPHA_NUMERIC = "ALPHANUMERIC";
	const RAND_ALPHA = "ALPHA";
	const RAND_NUMERIC = "NUMERIC";

	// Statuses
	const SUCCESS = "SUCCESS";
	const FAILED = "FAILED";

	// Users for reset password redirection.
	const CLIENT_DOMAIN = "https://elearning.firerecruitmentaustralia.com.au";
	const IMAGE_DOMAIN = "https://elearning.firerecruitmentaustralia.com.au/assets/images/uploads/";
	const SYSTEM = "SYSTEM";

}
