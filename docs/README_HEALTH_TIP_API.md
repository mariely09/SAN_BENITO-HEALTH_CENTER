# Health Tip API Setup Guide

This guide explains how to set up the Health Tip API integration for displaying daily motivational health quotes in the San Benito Health Center Management System.

## Overview

The Health Tip API integration provides:
- Daily motivational health quotes
- Inspirational messages for wellness
- Author attribution for quotes
- Automatic refresh functionality
- Random quote selection

## API Provider

**API Ninjas - Quotes API**
- Website: https://api.api-ninjas.com/
- Documentation: https://api.api-ninjas.com/v1/quotes
- Free tier: 50,000 requests/month
- Category: Inspirational quotes

## Setup Instructions

### 1. Get API Key

1. Visit https://api.api-ninjas.com/
2. Click "Sign Up" to create a free account
3. Verify your email address
4. Go to your dashboard to find your API key
5. Copy the API key for configuration

### 2. Configure API Key

1. Open `api/get_health_tip.php`
2. Replace `YOUR_API_KEY_HERE` with your actual API key:

```php
// Replace this with your actual API Ninjas API key
$api_key = 'your_actual_api_key_here';
```

### 3. Test the Integration

1. Open the resident dashboard
2. Check if the health tip widget loads properly
3. Verify that quotes and authors are displayed
4. Test the refresh button functionality

## API Configuration Details

### Current Settings

- **Category**: General inspirational quotes
- **Limit**: 1 quote per request
- **Language**: English
- **Format**: JSON response

### API Endpoint Used

```
https://api.api-ninjas.com/v1/quotes?category=inspirational
```

### Request Headers

```php
$headers = [
    'X-Api-Key: YOUR_API_KEY_HERE',
    'Content-Type: application/json'
];
```

### Response Data Structure

```json
[
  {
    "quote": "The only way to do great work is to love what you do.",
    "author": "Steve Jobs",
    "category": "inspirational"
  }
]
```

## Customization Options

### Change Quote Category

You can modify the quote category in `api/get_health_tip.php`:

```php
// Available categories
$category = 'inspirational';  // Current setting
$category = 'success';        // Success quotes
$category = 'motivational';   // Motivational quotes
$category = 'wisdom';         // Wisdom quotes
$category = 'happiness';      // Happiness quotes
$category = 'life';          // Life quotes
```

### Multiple Categories

To get quotes from multiple categories randomly:

```php
$categories = ['inspirational', 'success', 'motivational', 'wisdom'];
$random_category = $categories[array_rand($categories)];
$url = "https://api.api-ninjas.com/v1/quotes?category=" . $random_category;
```

### Limit Number of Quotes

To get multiple quotes per request:

```php
$url = "https://api.api-ninjas.com/v1/quotes?category=inspirational&limit=5";
```

## Error Handling

The system includes comprehensive error handling:

### Common Error Scenarios

1. **Invalid API Key**
   - Error: "Unauthorized access"
   - Solution: Check if API key is correct and active

2. **Rate Limit Exceeded**
   - Error: "API rate limit exceeded"
   - Solution: Wait for rate limit reset or upgrade plan

3. **Network Issues**
   - Error: "Failed to fetch health tip"
   - Solution: Check internet connection and API status

4. **No Quotes Available**
   - Error: "No quotes found for category"
   - Solution: Try different category or check API status

### Error Display

Errors are displayed in the health tip widget with:
- Error icon
- User-friendly error message
- Retry button for manual refresh

## Frontend Integration

### JavaScript Functions

The health tip data is fetched and displayed using:

```javascript
// Main function to fetch health tip
async function fetchHealthTip()

// Refresh button event listener
document.getElementById('refreshTipBtn').addEventListener('click', fetchHealthTip)

// Auto-load on page ready
document.addEventListener('DOMContentLoaded', fetchHealthTip)
```

### UI Components

- **Quote Display**: Shows the inspirational quote
- **Author Attribution**: Displays quote author
- **Refresh Button**: Manual refresh functionality
- **Loading State**: Shows spinner while fetching
- **Error State**: Displays error message with retry option

## Security Considerations

### API Key Protection

- API key is stored server-side in PHP
- Not exposed to client-side JavaScript
- Requests are proxied through PHP to hide API key

### Rate Limiting

- Implement caching to reduce API calls
- Consider storing quotes temporarily
- Monitor API usage to avoid exceeding limits

## Caching Implementation

To reduce API calls and improve performance:

### Simple File Caching

```php
// Cache quotes for 1 hour
$cache_file = 'cache/health_tips.json';
$cache_time = 3600; // 1 hour

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    // Use cached data
    $cached_data = json_decode(file_get_contents($cache_file), true);
    echo json_encode($cached_data);
    exit;
}

// Fetch new data and cache it
// ... API call code ...
file_put_contents($cache_file, json_encode($response_data));
```

### Database Caching

```sql
CREATE TABLE quote_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quote TEXT NOT NULL,
    author VARCHAR(255),
    category VARCHAR(100),
    cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Troubleshooting

### Health Tip Widget Not Loading

1. Check browser console for JavaScript errors
2. Verify API key is correctly configured
3. Test API endpoint directly with tools like Postman
4. Check if API Ninjas service is operational

### Empty or Invalid Quotes

1. Verify API key permissions
2. Check if selected category exists
3. Test with different categories
4. Ensure API response format is correct

### Performance Issues

1. Implement caching mechanism
2. Reduce refresh frequency
3. Consider pre-loading quotes
4. Use local fallback quotes

## API Limits and Pricing

### Free Tier Limits

- **Requests per month**: 50,000
- **Rate limit**: 100 requests per minute
- **Categories**: All available
- **Support**: Community support

### Paid Plans

- **Starter**: $5/month (100,000 requests)
- **Pro**: $15/month (500,000 requests)
- **Enterprise**: Custom pricing for higher volumes

## Alternative Quote APIs

If API Ninjas doesn't meet your needs:

1. **QuoteGarden** - Free with attribution
2. **Quotable** - Free, open-source API
3. **They Said So** - Free tier available
4. **ZenQuotes** - Free with rate limits

## Fallback Quotes

For offline functionality, consider adding fallback quotes:

```php
$fallback_quotes = [
    [
        'quote' => 'Health is not about the weight you lose, but about the life you gain.',
        'author' => 'Unknown'
    ],
    [
        'quote' => 'Take care of your body. It\'s the only place you have to live.',
        'author' => 'Jim Rohn'
    ],
    [
        'quote' => 'A healthy outside starts from the inside.',
        'author' => 'Robert Urich'
    ]
];
```

## Custom Health Tips

You can also create custom health tips specific to your community:

```php
$custom_health_tips = [
    [
        'quote' => 'Regular check-ups at your barangay health center can prevent serious health issues.',
        'author' => 'San Benito Health Center'
    ],
    [
        'quote' => 'Vaccination protects not just you, but your entire community.',
        'author' => 'Department of Health'
    ]
];
```

## Support and Resources

- **API Ninjas Documentation**: https://api.api-ninjas.com/v1/quotes
- **API Status**: Check their website for service status
- **Support**: Contact through their website
- **Rate Limits**: Monitor usage in your dashboard

## Changelog

- **v1.0**: Initial health tip API integration
- **v1.1**: Added error handling and retry functionality
- **v1.2**: Improved UI with loading states and animations
- **v1.3**: Added fallback quotes for offline functionality

---

For technical support or questions about the health tip API integration, please refer to the main system documentation or contact the development team.