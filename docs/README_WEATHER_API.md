# Weather API Setup Guide

This guide explains how to set up the OpenWeatherMap API integration for displaying real-time weather information in the San Benito Health Center Management System.

## Overview

The Weather API integration provides:
- Real-time weather data for Manila, Philippines
- Temperature display in Celsius
- Weather conditions and descriptions
- Humidity and wind speed information
- Weather icons from OpenWeatherMap
- Automatic refresh functionality

## API Provider

**OpenWeatherMap API**
- Website: https://openweathermap.org/api
- Documentation: https://openweathermap.org/current
- Free tier: 1,000 calls/day, 60 calls/minute

## Setup Instructions

### 1. Get API Key

1. Visit https://openweathermap.org/api
2. Click "Sign Up" to create a free account
3. Verify your email address
4. Go to "API Keys" section in your account
5. Copy your default API key (or generate a new one)
6. Wait 10-15 minutes for the API key to activate

### 2. Configure API Key

1. Open `api/get_weather.php`
2. Replace `YOUR_API_KEY_HERE` with your actual API key:

```php
// Replace this with your actual OpenWeatherMap API key
$api_key = 'your_actual_api_key_here';
```

### 3. Test the Integration

1. Open the resident dashboard
2. Check if the weather widget loads properly
3. Verify that temperature, humidity, and wind data are displayed
4. Test the refresh button functionality

## API Configuration Details

### Current Settings

- **Location**: Manila, Philippines (`Manila,PH`)
- **Units**: Metric (Celsius, m/s)
- **Language**: English
- **Data**: Current weather conditions

### API Endpoint Used

```
https://api.openweathermap.org/data/2.5/weather?q=Manila,PH&appid={API_KEY}&units=metric
```

### Response Data Structure

```json
{
  "coord": {"lon": 120.9822, "lat": 14.6042},
  "weather": [
    {
      "id": 801,
      "main": "Clouds",
      "description": "few clouds",
      "icon": "02d"
    }
  ],
  "main": {
    "temp": 28.5,
    "humidity": 78
  },
  "wind": {
    "speed": 3.2
  },
  "name": "Manila"
}
```

## Customization Options

### Change Location

To display weather for a different city, modify the `$city` variable in `api/get_weather.php`:

```php
// For other Philippine cities
$city = 'Cebu,PH';        // Cebu
$city = 'Davao,PH';       // Davao
$city = 'Baguio,PH';      // Baguio

// For international cities
$city = 'Tokyo,JP';       // Tokyo, Japan
$city = 'Singapore,SG';   // Singapore
```

### Change Temperature Units

To use Fahrenheit instead of Celsius:

```php
$units = 'imperial';  // For Fahrenheit
// $units = 'metric';  // For Celsius (current)
```

### Add More Weather Data

You can extend the API response to include additional data:

```php
// Add these fields to the response array
'feels_like' => $data['main']['feels_like'],
'pressure' => $data['main']['pressure'],
'visibility' => $data['visibility'],
'sunrise' => date('H:i', $data['sys']['sunrise']),
'sunset' => date('H:i', $data['sys']['sunset'])
```

## Error Handling

The system includes comprehensive error handling:

### Common Error Scenarios

1. **Invalid API Key**
   - Error: "Invalid API key"
   - Solution: Check if API key is correct and activated

2. **Rate Limit Exceeded**
   - Error: "API rate limit exceeded"
   - Solution: Wait for rate limit reset or upgrade plan

3. **Network Issues**
   - Error: "Failed to fetch weather data"
   - Solution: Check internet connection and API status

4. **Invalid City**
   - Error: "City not found"
   - Solution: Use correct city format (City,CountryCode)

### Error Display

Errors are displayed in the weather widget with:
- Error icon
- User-friendly error message
- Retry button for manual refresh

## Frontend Integration

### JavaScript Functions

The weather data is fetched and displayed using:

```javascript
// Main function to fetch weather data
async function fetchWeatherData()

// Refresh button event listener
document.getElementById('refreshWeatherBtn').addEventListener('click', fetchWeatherData)

// Auto-load on page ready
document.addEventListener('DOMContentLoaded', fetchWeatherData)
```

### UI Components

- **Weather Icon**: Displays current weather condition
- **Temperature**: Shows temperature in Celsius
- **Location**: Displays city name
- **Humidity**: Shows humidity percentage
- **Wind Speed**: Shows wind speed in m/s
- **Refresh Button**: Manual refresh functionality

## Security Considerations

### API Key Protection

- API key is stored server-side in PHP
- Not exposed to client-side JavaScript
- Requests are proxied through PHP to hide API key

### Rate Limiting

- Implement caching to reduce API calls
- Consider storing weather data temporarily
- Monitor API usage to avoid exceeding limits

## Troubleshooting

### Weather Widget Not Loading

1. Check browser console for JavaScript errors
2. Verify API key is correctly configured
3. Test API endpoint directly in browser
4. Check if OpenWeatherMap service is operational

### Incorrect Weather Data

1. Verify city name and country code
2. Check if coordinates are correct
3. Ensure units parameter is set properly

### Performance Issues

1. Implement caching mechanism
2. Reduce refresh frequency
3. Consider using local weather data sources

## API Limits and Pricing

### Free Tier Limits

- **Calls per day**: 1,000
- **Calls per minute**: 60
- **Historical data**: Not available
- **Forecasts**: Current weather only

### Paid Plans

- **Startup**: $40/month (100,000 calls/month)
- **Developer**: $180/month (1,000,000 calls/month)
- **Professional**: $600/month (5,000,000 calls/month)

## Alternative Weather APIs

If OpenWeatherMap doesn't meet your needs:

1. **WeatherAPI.com** - Free tier: 1M calls/month
2. **AccuWeather** - Limited free tier
3. **Weather Underground** - IBM Weather service
4. **Visual Crossing** - 1,000 free queries/day

## Support and Resources

- **OpenWeatherMap Documentation**: https://openweathermap.org/api
- **API Status Page**: https://status.openweathermap.org/
- **Support**: https://openweathermap.org/support
- **Community Forum**: https://openweathermap.org/community

## Changelog

- **v1.0**: Initial weather API integration
- **v1.1**: Added error handling and retry functionality
- **v1.2**: Improved UI with loading states and animations

---

For technical support or questions about the weather API integration, please refer to the main system documentation or contact the development team.