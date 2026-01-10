<?php

namespace app\Enums;

enum ErrorCode: int
{
    /** Invalid request syntax, malformed parameters, or validation failure */
    case BAD_REQUEST = 400;

    /** Authentication required or invalid credentials provided */
    case UNAUTHORIZED = 401;

    /** Payment or subscription required to access this resource */
    case PAYMENT_REQUIRED = 402;

    /** Valid authentication but insufficient permissions */
    case FORBIDDEN = 403;

    /** Requested resource or page does not exist */
    case NOT_FOUND = 404;

    /** HTTP method (GET, POST, etc.) not supported for this endpoint */
    case METHOD_NOT_ALLOWED = 405;

    /** Server cannot produce response matching client's Accept headers */
    case NOT_ACCEPTABLE = 406;

    /** Client took too long to send the complete request */
    case REQUEST_TIMEOUT = 408;

    /** Request conflicts with current state (duplicate, version mismatch) */
    case CONFLICT = 409;

    /** Resource permanently deleted and will not return */
    case GONE = 410;

    /** Request body or uploaded file exceeds size limit */
    case PAYLOAD_TOO_LARGE = 413;

    /** Request URL exceeds maximum allowed length */
    case URI_TOO_LONG = 414;

    /** Request content type or file format not supported */
    case UNSUPPORTED_MEDIA_TYPE = 415;

    /** Rate limit exceeded, too many requests in time window */
    case TOO_MANY_REQUESTS = 429;

    /** Unexpected server error or unhandled exception occurred */
    case INTERNAL_ERROR = 500;

    /** Feature or endpoint not yet implemented */
    case NOT_IMPLEMENTED = 501;

    /** Invalid response from upstream server or proxy */
    case BAD_GATEWAY = 502;

    /** Server temporarily unavailable (maintenance, overload) */
    case SERVICE_UNAVAILABLE = 503;

    /** Upstream server failed to respond in time */
    case GATEWAY_TIMEOUT = 504;

    public function message(): string
    {
        return match ($this) {
            self::BAD_REQUEST => 'Bad request.',
            self::UNAUTHORIZED => 'You are not authorized to view this page.',
            self::PAYMENT_REQUIRED => 'Payment is required to access this resource.',
            self::FORBIDDEN => 'You have no access to this page.',
            self::NOT_FOUND => 'This page does not exist.',
            self::METHOD_NOT_ALLOWED => 'This method is not allowed.',
            self::NOT_ACCEPTABLE => 'The requested content type is not acceptable.',
            self::REQUEST_TIMEOUT => 'Your request has timed out.',
            self::CONFLICT => 'There was a conflict with your request.',
            self::GONE => 'This resource is no longer available.',
            self::PAYLOAD_TOO_LARGE => 'The uploaded file is too large.',
            self::URI_TOO_LONG => 'The requested URL is too long.',
            self::UNSUPPORTED_MEDIA_TYPE => 'This file type is not supported.',
            self::TOO_MANY_REQUESTS => 'Too many requests. Please try again later.',
            self::INTERNAL_ERROR => 'An internal server error occurred.',
            self::NOT_IMPLEMENTED => 'This feature is not yet implemented.',
            self::BAD_GATEWAY => 'Bad gateway error.',
            self::SERVICE_UNAVAILABLE => 'Service is temporarily unavailable.',
            self::GATEWAY_TIMEOUT => 'Gateway timeout error.'
        };
    }
}
