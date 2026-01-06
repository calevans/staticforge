---
title: 'API Documentation - Authentication'
description: 'Learn how to authenticate with our API'
category: documentation
tags:
  - api
  - authentication
  - security
  - developers
---
# API Authentication

This is a dummy guide
This guide explains how to authenticate requests to our API.

## Overview

All API requests require authentication using an API key. The key must be included in the `Authorization` header of each request.

## Getting an API Key

1. Log in to your account dashboard
2. Navigate to **Settings** → **API Keys**
3. Click **Generate New Key**
4. Copy your key and store it securely

> **Warning**: Never share your API key or commit it to version control. Treat it like a password.

## Making Authenticated Requests

### Using cURL

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
     https://api.example.com/v1/users
```

### Using PHP

```php
<?php

$apiKey = 'YOUR_API_KEY';

$ch = curl_init('https://api.example.com/v1/users');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$data = json_decode($response, true);

curl_close($ch);
```

### Using JavaScript

```javascript
const apiKey = 'YOUR_API_KEY';

fetch('https://api.example.com/v1/users', {
  headers: {
    'Authorization': `Bearer ${apiKey}`,
    'Content-Type': 'application/json'
  }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));
```

## Response Codes

The API uses standard HTTP status codes:

| Code | Description |
|------|-------------|
| `200` | Success |
| `201` | Created |
| `400` | Bad Request |
| `401` | Unauthorized (invalid API key) |
| `403` | Forbidden (insufficient permissions) |
| `404` | Not Found |
| `429` | Too Many Requests (rate limit exceeded) |
| `500` | Internal Server Error |

## Rate Limiting

API requests are rate-limited to:

- **Free tier**: 100 requests per hour
- **Pro tier**: 1,000 requests per hour
- **Enterprise**: Custom limits

Rate limit headers are included in all responses:

```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1640995200
```

## Error Responses

Errors are returned in JSON format:

```json
{
  "error": {
    "code": "unauthorized",
    "message": "Invalid API key",
    "details": "The API key provided is not valid or has been revoked"
  }
}
```

## Best Practices

1. **Store keys securely**: Use environment variables, never hardcode
2. **Rotate keys regularly**: Generate new keys periodically
3. **Use HTTPS**: Always use HTTPS for API requests
4. **Handle errors**: Implement proper error handling in your code
5. **Respect rate limits**: Implement backoff strategies

## Next Steps

- API Reference
- [Code Examples](/examples.html)
- Webhooks Guide

## Support

Need help? Contact our support team:

- **Email**: api-support@example.com
- **Docs**: Full documentation
