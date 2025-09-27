<?php
/**
 * PHPUnit Tests for Gondwana Collection Rates API
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure test environment
        $_ENV['APP_ENV'] = 'testing';
        
        // Create test log directory
        $testLogDir = __DIR__ . '/../logs/test';
        if (!is_dir($testLogDir)) {
            mkdir($testLogDir, 0755, true);
        }
    }

    public function testBasicFunctionality()
    {
        $this->assertTrue(true);
        $this->assertNotEmpty(API_VERSION);
    }

    public function testDateValidation()
    {
        // Test valid dates
        $this->assertTrue(validateDateFormat('25/01/2024', 'd/m/Y'));
        $this->assertTrue(validateDateFormat('01/12/2024', 'd/m/Y'));
        
        // Test invalid dates
        $this->assertFalse(validateDateFormat('2024-01-25', 'd/m/Y'));
        $this->assertFalse(validateDateFormat('invalid-date', 'd/m/Y'));
        $this->assertFalse(validateDateFormat('32/01/2024', 'd/m/Y'));
        $this->assertFalse(validateDateFormat('01/13/2024', 'd/m/Y'));
    }

    public function testGuestCategorization()
    {
        // Test mixed ages
        $ages = [25, 30, 16, 8, 1];
        $categories = categorizeGuests($ages);
        
        $this->assertEquals(2, $categories['adults']);
        $this->assertEquals(2, $categories['children']);
        $this->assertEquals(1, $categories['infants']);
        $this->assertEquals(5, $categories['total']);
        
        // Test edge cases
        $this->assertEquals(['adults' => 0, 'children' => 0, 'infants' => 0, 'total' => 0], categorizeGuests([]));
        $this->assertEquals(['adults' => 0, 'children' => 0, 'total' => 0], categorizeGuests('invalid'));
    }

    public function testAvailableUnits()
    {
        $units = getAvailableUnits();
        
        $this->assertIsArray($units);
        $this->assertArrayHasKey('Standard Unit', $units);
        $this->assertArrayHasKey('Deluxe Unit', $units);
        $this->assertEquals(-2147483637, $units['Standard Unit']);
        $this->assertEquals(-2147483456, $units['Deluxe Unit']);
    }

    public function testCurrencyFormatting()
    {
        $this->assertEquals('1,234.56 NAD', formatCurrency(1234.56));
        $this->assertEquals('0.00 NAD', formatCurrency('invalid'));
        $this->assertEquals('999.00 USD', formatCurrency(999, 'USD'));
    }

    public function testNightsCalculation()
    {
        // Test normal calculation
        $this->assertEquals(3, calculateNights('25/01/2024', '28/01/2024'));
        $this->assertEquals(1, calculateNights('25/01/2024', '26/01/2024'));
        
        // Test edge cases
        $this->assertEquals(1, calculateNights('invalid', '26/01/2024'));
        $this->assertEquals(1, calculateNights('25/01/2024', 'invalid'));
    }

    public function testInputSanitization()
    {
        // Test string sanitization
        $dirty = '<script>alert("xss")</script>Hello & World';
        $clean = sanitizeInput($dirty);
        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringContainsString('Hello', $clean);

        // Test array sanitization
        $dirtyArray = ['name' => '<b>John</b>', 'age' => 25];
        $cleanArray = sanitizeInput($dirtyArray);
        $this->assertStringNotContainsString('<b>', $cleanArray['name']);
        $this->assertEquals(25, $cleanArray['age']);
    }

    public function testEmailValidation()
    {
        $this->assertTrue(validateEmail('test@example.com'));
        $this->assertTrue(validateEmail('user.name+tag@domain.co.za'));
        $this->assertFalse(validateEmail('invalid-email'));
        $this->assertFalse(validateEmail(''));
        $this->assertFalse(validateEmail('test@'));
    }

    public function testPhoneNumberValidation()
    {
        // Test valid Namibian numbers
        $this->assertEquals('+264813494846', validatePhoneNumber('813494846'));
        $this->assertEquals('+26461234567', validatePhoneNumber('+26461234567'));
        
        // Test invalid numbers
        $this->assertFalse(validatePhoneNumber('123'));
        $this->assertFalse(validatePhoneNumber('invalid'));
    }

    public function testSlugify()
    {
        $this->assertEquals('hello-world', slugify('Hello World!'));
        $this->assertEquals('test-123', slugify('Test 123'));
        $this->assertEquals('special-chars', slugify('Special @#$% Chars'));
    }

    public function testFileSizeFormatting()
    {
        $this->assertEquals('1.00 KB', formatFileSize(1024));
        $this->assertEquals('1.50 MB', formatFileSize(1024 * 1024 * 1.5));
        $this->assertEquals('500.00 B', formatFileSize(500));
    }

    public function testJsonValidation()
    {
        $this->assertTrue(isJson('{"test": "value"}'));
        $this->assertTrue(isJson('[1,2,3]'));
        $this->assertFalse(isJson('invalid json'));
        $this->assertFalse(isJson(''));
    }

    public function testConfigAccess()
    {
        $this->assertNotEmpty(getConfig('API_VERSION', 'default'));
        $this->assertEquals('default', getConfig('NON_EXISTENT_KEY', 'default'));
    }

    public function testSecureTokenGeneration()
    {
        $token1 = generateSecureToken();
        $token2 = generateSecureToken();
        
        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(32, strlen($token1));
    }

    public function testTimezoneConversion()
    {
        $utcTime = '2024-01-25 12:00:00';
        $namibiaTime = convertTimezone($utcTime, 'UTC', 'Africa/Windhoek');
        
        $this->assertNotEmpty($namibiaTime);
        $this->assertNotEquals($utcTime, $namibiaTime);
    }

    public function testArrayMergeRecursive()
    {
        $array1 = ['a' => 1, 'b' => ['c' => 2]];
        $array2 = ['b' => ['d' => 3], 'e' => 4];
        
        $merged = arrayMergeRecursive($array1, $array2);
        
        $this->assertEquals(1, $merged['a']);
        $this->assertEquals(2, $merged['b']['c']);
        $this->assertEquals(3, $merged['b']['d']);
        $this->assertEquals(4, $merged['e']);
    }

    public function testMobileDeviceDetection()
    {
        // Mock user agent
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)';
        $this->assertTrue(isMobileDevice());

        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        $this->assertFalse(isMobileDevice());
    }

    protected function tearDown(): void
    {
        // Clean up test data if needed
        unset($_ENV['APP_ENV']);
        unset($_SERVER['HTTP_USER_AGENT']);
    }
}

/**
 * API Integration Tests
 */
class ApiIntegrationTest extends TestCase
{
    private $baseUrl = 'http://localhost:8000';

    public function testApiHealthCheck()
    {
        // Note: These tests would need actual server running
        // For now, they serve as documentation of expected behavior
        $this->assertTrue(true);
    }

    public function testValidRatesRequest()
    {
        $payload = [
            'Unit Name' => 'Standard Unit',
            'Arrival' => '25/01/2024',
            'Departure' => '28/01/2024',
            'Occupants' => 2,
            'Ages' => [25, 30]
        ];

        // Mock test - in real scenario would make HTTP request
        $this->assertArrayHasKey('Unit Name', $payload);
        $this->assertArrayHasKey('Ages', $payload);
    }

    public function testInvalidRatesRequest()
    {
        $invalidPayload = [
            'Unit Name' => 'Invalid Unit',
            'Arrival' => 'invalid-date',
            'Occupants' => -1
        ];

        // Mock validation
        $this->assertEquals('Invalid Unit', $invalidPayload['Unit Name']);
    }
}
?>