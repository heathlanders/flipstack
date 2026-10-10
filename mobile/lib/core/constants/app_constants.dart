import '../config/app_config.dart';

// App Constants - Complete with all missing strings
class AppConstants {
  // API Configuration - uses environment-based config
  // Set at build time: flutter run --dart-define=API_BASE_URL=https://flipstack.app
  static const String baseUrl = AppConfig.baseUrl;
  static const String apiVersion = 'api';
  static const String apiBaseUrl = AppConfig.apiBaseUrl;

  // Helper for categories endpoint
  static const String categoriesEndpoint = '$apiBaseUrl/products/categories';
  
  // Storage keys
  static const String tokenKey = 'auth_token';
  static const String refreshTokenKey = 'refresh_token';
  static const String userKey = 'user_data';
  static const String themeKey = 'theme_mode';
  static const String languageKey = 'language_code';
  static const String accessTokenKey = 'access_token_key';
  static const String userDataKey = 'user_data_key';  
  static const String isLoggedInKey = 'is_logged_in_key';

  // App Info
  static const String appName = 'E-Commerce App';
  static const String appVersion = '1.0.0';
  static const int appBuildNumber = 1;
  
  // Pagination
  static const int defaultPageSize = 20;
  static const int maxPageSize = 100;
  
  // Timeouts (in milliseconds)
  static const int connectionTimeout = 30000;
  static const int receiveTimeout = 30000;
  static const int sendTimeout = 30000;
  
  // File Upload
  static const int maxFileSize = 10 * 1024 * 1024; // 10MB
  static const List<String> allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
  
  // Validation
  static const int minPasswordLength = 8;
  static const int maxPasswordLength = 128;
  static const int maxNameLength = 100;
  static const int maxEmailLength = 255;
  
  // Business Rules
  static const int lowStockThreshold = 10;
  static const int outOfStockThreshold = 0;
  static const double maxDiscountPercentage = 100.0;
  static const double minPrice = 0.01;
  
  // Cache durations (in minutes)
  static const int shortCacheDuration = 5;
  static const int mediumCacheDuration = 30;
  static const int longCacheDuration = 1440; // 24 hours
}

// API Endpoints
class ApiEndpoints {
  // Base
  static const String api = '${AppConstants.apiBaseUrl}';

  // Authentication
  static const String login = '$api/auth/login';
  static const String register = '$api/auth/register';
  static const String logout = '$api/auth/logout';
  static const String refresh = '$api/auth/refresh';
  static const String forgotPassword = '$api/auth/forgot-password';
  static const String resetPassword = '$api/auth/reset-password';
  static const String verifyEmail = '$api/auth/verify-email';
  static const String resendVerification = '$api/auth/resend-verification';
  
  // User Management
  static const String profile = '$api/user/profile';
  static const String updateProfile = '$api/user/profile';
  static const String changePassword = '$api/user/change-password';
  static const String deleteAccount = '$api/user/delete-account';
  static const String userSettings = '$api/user/settings';
  static const String userAddresses = '$api/user/addresses';
  static const String userOrders = '$api/user/orders';
  
  // Products
  static const String products = '$api/products';
  static const String productCategories = '$api/product-categories';
  static const String productSearch = '$api/products/search';
  static const String productReviews = '$api/products/reviews';
  static const String featuredProducts = '$api/products/featured';
  static const String popularProducts = '$api/products/popular';
  static const String categoriesEndpoint = '$api/product-categories';
  static const String refreshToken = '$api/auth/refresh';
  
  // Inventory (FIXED: This was causing the error)
  static const String inventory = '$api/inventory';
  static const String inventoryLevels = '$api/inventory/levels';
  static const String inventoryMovements = '$api/inventory/movements';
  static const String lowStockItems = '$api/inventory/low-stock';
  static const String outOfStockItems = '$api/inventory/out-of-stock';
  
  // Orders
  static const String orders = '$api/orders';
  static const String orderStatistics = '$api/orders/statistics';
  static const String orderHistory = '$api/orders/history';
  static const String orderTracking = '$api/orders/tracking';
  static const String orderInvoice = '$api/orders/invoice';
  
  // Fulfillment
  static const String pickList = '$api/fulfillment/pick';
  static const String fulfillmentOrders = '$api/fulfillment/orders';
  static const String packQueue = '$api/fulfillment/pack';
  static const String shipQueue = '$api/fulfillment/ship';
  static const String returns = '$api/fulfillment/returns';

  // Inventory Operations
  static const String inventoryLevelsApi = '$api/inventory/levels';
  static const String inventoryAdjust = '$api/inventory/adjust';
  static const String inventoryTransfer = '$api/inventory/transfer';
  static const String inventoryMovementsApi = '$api/inventory/movements';

  // Marketplace
  static const String marketplaceStatus = '$api/marketplace/status';

  // Categories & Locations CRUD
  static const String categories = '$api/categories';
  static const String locationsCrud = '$api/locations';

  // Barcode Lookup
  static const String barcodeLookup = '$api/products/barcode-lookup';

  // Analytics (legacy)
  static const String inventoryAnalytics = '$api/inventory/analytics';
  static const String inventoryValuation = '$api/inventory/valuation';

  // Analytics API (new)
  static const String analyticsKpis = '$api/analytics/kpis';
  static const String analyticsStockHealth = '$api/analytics/stock-health';
  static const String analyticsAbc = '$api/analytics/abc';
  static const String analyticsTurnover = '$api/analytics/turnover';
  static const String analyticsTopPerformers = '$api/analytics/top-performers';
  static const String analyticsMovementTrends = '$api/analytics/movement-trends';

  // eBay detail endpoints
  static const String ebayStatus = '$api/ebay/status';
  static const String ebayStats = '$api/ebay/stats';
  static const String ebayLogs = '$api/ebay/logs';
  static const String ebayListings = '$api/ebay/listings';
  static const String ebaySyncProduct = '$api/ebay/sync/product';

  // Cart
  static const String cart = '$api/cart';
  static const String cartItems = '$api/cart/items';
  static const String cartCount = '$api/cart/count';
  static const String cartClear = '$api/cart/clear';
  
  // Wishlist
  static const String wishlist = '$api/wishlist';
  static const String wishlistItems = '$api/wishlist/items';
  
  // Analytics
  static const String analytics = '$api/analytics';
  static const String dashboard = '$api/dashboard';
  static const String reports = '$api/reports';
  
  // File Upload
  static const String uploadImage = '$api/upload/image';
  static const String uploadFile = '$api/upload/file';
  static const String deleteFile = '$api/upload/delete';
}

// Application Strings - COMPLETE SET
class AppStrings {
  // Authentication
  static const String welcomeBack = 'Welcome Back!';
  static const String signInToContinue = 'Sign in to continue to your account';
  static const String emailAddress = 'Email Address';
  static const String password = 'Password';
  static const String confirmPassword = 'Confirm Password';
  static const String firstName = 'First Name';
  static const String lastName = 'Last Name';
  static const String phoneNumber = 'Phone Number';
  static const String rememberMe = 'Remember Me';
  static const String forgotPassword = 'Forgot Password?';
  static const String dontHaveAccount = "Don't have an account?";
  static const String alreadyHaveAccount = 'Already have an account?';
  static const String signUp = 'Sign Up';
  static const String signIn = 'Sign In';
  static const String signOut = 'Sign Out';
  static const String createAccount = 'Create Account';
  static const String resetPassword = 'Reset Password';
  static const String email = 'Email';
  static const String login = 'Login';  
  static const String register = 'Register';
  static const String appName = 'E-Commerce App';
  // Validation
  static const String fieldRequired = 'This field is required';
  static const String invalidEmail = 'Please enter a valid email address';
  static const String passwordTooShort = 'Password must be at least 8 characters';
  static const String passwordsDoNotMatch = 'Passwords do not match';
  static const String invalidPhoneNumber = 'Please enter a valid phone number';
  
  // General Actions
  static const String save = 'Save';
  static const String cancel = 'Cancel';
  static const String delete = 'Delete';
  static const String edit = 'Edit';
  static const String update = 'Update';
  static const String create = 'Create';
  static const String add = 'Add';
  static const String remove = 'Remove';
  static const String search = 'Search';
  static const String filter = 'Filter';
  static const String sort = 'Sort';
  static const String apply = 'Apply';
  static const String reset = 'Reset';
  static const String clear = 'Clear';
  static const String confirm = 'Confirm';
  static const String yes = 'Yes';
  static const String no = 'No';
  static const String ok = 'OK';
  static const String done = 'Done';
  static const String close = 'Close';
  static const String back = 'Back';
  static const String next = 'Next';
  static const String previous = 'Previous';
  static const String loading = 'Loading...';
  static const String refreshing = 'Refreshing...';
  static const String processing = 'Processing...';
  
  // App Description
  static const String appDescription = 'Your complete e-commerce solution for managing inventory, orders, and customer relationships.';
  static const String appTagline = 'Streamline your business operations';
  
  // Success Messages
  static const String loginSuccess = 'Logged in successfully!';
  static const String logoutSuccess = 'Logged out successfully!';
  static const String registrationSuccess = 'Account created successfully!';
  static const String profileUpdated = 'Profile updated successfully!';
  static const String passwordChanged = 'Password changed successfully!';
  static const String orderPlaced = 'Order placed successfully!';
  static const String productAdded = 'Product added successfully!';
  static const String productUpdated = 'Product updated successfully!';
  static const String productDeleted = 'Product deleted successfully!';
  
  // Error Messages
  static const String loginFailed = 'Login failed. Please check your credentials.';
  static const String registrationFailed = 'Registration failed. Please try again.';
  static const String networkError = 'Network error. Please check your connection.';
  static const String serverError = 'Server error. Please try again later.';
  static const String unknownError = 'An unknown error occurred.';
  static const String invalidCredentials = 'Invalid email or password.';
  static const String accountDisabled = 'Account has been disabled.';
  static const String emailNotVerified = 'Please verify your email address.';
  static const String sessionExpired = 'Your session has expired. Please sign in again.';
  
  // Navigation
  static const String home = 'Home';
  static const String products = 'Products';
  static const String orders = 'Orders';
  static const String inventory = 'Inventory';
  static const String customers = 'Customers';
  static const String analytics = 'Analytics';
  static const String settings = 'Settings';
  static const String profile = 'Profile';
  static const String notifications = 'Notifications';
  static const String help = 'Help';
  static const String about = 'About';
  static const String contact = 'Contact';
  
  // Product Related
  static const String productName = 'Product Name';
  static const String productDescription = 'Product Description';
  static const String productPrice = 'Price';
  static const String productStock = 'Stock';
  static const String productCategory = 'Category';
  static const String productSKU = 'SKU';
  static const String productBarcode = 'Barcode';
  static const String productImages = 'Product Images';
  static const String productDetails = 'Product Details';
  static const String addToCart = 'Add to Cart';
  static const String buyNow = 'Buy Now';
  static const String outOfStock = 'Out of Stock';
  static const String lowStock = 'Low Stock';
  static const String inStock = 'In Stock';
  static const String featuredProducts = 'Featured Products';
  static const String popularProducts = 'Popular Products';
  static const String newProducts = 'New Products';
  static const String saleProducts = 'Sale Products';
  
  // Order Related
  static const String orderNumber = 'Order Number';
  static const String orderDate = 'Order Date';
  static const String orderStatus = 'Order Status';
  static const String orderTotal = 'Order Total';
  static const String orderItems = 'Order Items';
  static const String orderHistoryLabel = 'Order History';
  static const String orderDetails = 'Order Details';
  static const String trackOrder = 'Track Order';
  static const String cancelOrder = 'Cancel Order';
  static const String returnOrder = 'Return Order';
  static const String refundOrder = 'Refund Order';
  static const String orderConfirmed = 'Order Confirmed';
  static const String orderPending = 'Order Pending';
  static const String orderProcessing = 'Order Processing';
  static const String orderShipped = 'Order Shipped';
  static const String orderDelivered = 'Order Delivered';
  static const String orderCancelled = 'Order Cancelled';
  
  // Inventory Related
  static const String stockLevel = 'Stock Level';
  static const String stockAlert = 'Stock Alert';
  static const String reorderPoint = 'Reorder Point';
  static const String lastUpdated = 'Last Updated';
  static const String inventoryValue = 'Inventory Value';
  static const String totalProducts = 'Total Products';
  static const String activeProducts = 'Active Products';
  static const String inactiveProducts = 'Inactive Products';
  
  // Cart Related
  static const String cart = 'Cart';
  static const String cartItems = 'Cart Items';
  static const String cartEmpty = 'Your cart is empty';
  static const String cartTotal = 'Cart Total';
  static const String subtotal = 'Subtotal';
  static const String tax = 'Tax';
  static const String shipping = 'Shipping';
  static const String discount = 'Discount';
  static const String total = 'Total';
  static const String checkout = 'Checkout';
  static const String removeFromCart = 'Remove from Cart';
  static const String updateQuantity = 'Update Quantity';
  static const String quantity = 'Quantity';
  
  // User Profile
  static const String personalInformation = 'Personal Information';
  static const String contactInformation = 'Contact Information';
  static const String billingAddress = 'Billing Address';
  static const String shippingAddress = 'Shipping Address';
  static const String paymentMethods = 'Payment Methods';
  static const String orderHistory = 'Order History';
  static const String accountSettings = 'Account Settings';
  static const String privacySettings = 'Privacy Settings';
  static const String notificationSettings = 'Notification Settings';
  static const String changePassword = 'Change Password';
  static const String deleteAccount = 'Delete Account';
  
  // Search and Filter
  static const String searchProducts = 'Search products...';
  static const String searchResults = 'Search Results';
  static const String noResults = 'No results found';
  static const String filterBy = 'Filter By';
  static const String sortBy = 'Sort By';
  static const String priceRange = 'Price Range';
  static const String category = 'Category';
  static const String brand = 'Brand';
  static const String rating = 'Rating';
  static const String availability = 'Availability';
  
  // Date and Time
  static const String today = 'Today';
  static const String yesterday = 'Yesterday';
  static const String thisWeek = 'This Week';
  static const String thisMonth = 'This Month';
  static const String thisYear = 'This Year';
  static const String lastWeek = 'Last Week';
  static const String lastMonth = 'Last Month';
  static const String lastYear = 'Last Year';
  
  // Status Messages
  static const String active = 'Active';
  static const String inactive = 'Inactive';
  static const String enabled = 'Enabled';
  static const String disabled = 'Disabled';
  static const String published = 'Published';
  static const String draft = 'Draft';
  static const String pending = 'Pending';
  static const String approved = 'Approved';
  static const String rejected = 'Rejected';
}

// Order Status Enum
enum OrderStatus {
  pending,
  confirmed,
  processing,
  shipped,
  delivered,
  cancelled,
  returned,
  refunded
}

// Product Category Enum - RESOLVED CONFLICT
enum ProductCategory {
  electronics,
  clothing,
  books,
  homeGarden,
  sportsOutdoors,
  toysGames,
  healthBeauty,
  automotive,
  food,
  other
}

// Product Status
enum ProductStatus {
  active,
  inactive,
  outOfStock,
  discontinued
}

// User Role
enum UserRole {
  admin,
  manager,
  staff,
  customer
}

// Sort Options
enum SortOption {
  nameAsc,
  nameDesc,
  priceAsc,
  priceDesc,
  dateAsc,
  dateDesc,
  popularityAsc,
  popularityDesc,
  stockAsc,    
  stockDesc
}

// View Mode
enum ViewMode {
  grid,
  list
}

// Theme Mode
enum ThemeMode {
  system,
  light,
  dark
}

// Language
enum Language {
  english,
  spanish,
  french,
  german,
  chinese,
  japanese
}

// Currency
enum Currency {
  usd,
  eur,
  gbp,
  jpy,
  cny
}

// Payment Method
enum PaymentMethod {
  creditCard,
  debitCard,
  paypal,
  applePay,
  googlePay,
  bankTransfer,
  cash
}

// Shipping Method
enum ShippingMethod {
  standard,
  express,
  overnight,
  pickup
}

// File Type
enum FileType {
  image,
  document,
  video,
  audio,
  other
}

// Notification Type
enum NotificationType {
  info,
  success,
  warning,
  error
}

// Extensions for enums
extension OrderStatusExtension on OrderStatus {
  String get displayName {
    switch (this) {
      case OrderStatus.pending:
        return 'Pending';
      case OrderStatus.confirmed:
        return 'Confirmed';
      case OrderStatus.processing:
        return 'Processing';
      case OrderStatus.shipped:
        return 'Shipped';
      case OrderStatus.delivered:
        return 'Delivered';
      case OrderStatus.cancelled:
        return 'Cancelled';
      case OrderStatus.returned:
        return 'Returned';
      case OrderStatus.refunded:
        return 'Refunded';
    }
  }
}

extension ProductCategoryExtension on ProductCategory {
  String get displayName {
    switch (this) {
      case ProductCategory.electronics:
        return 'Electronics';
      case ProductCategory.clothing:
        return 'Clothing';
      case ProductCategory.books:
        return 'Books';
      case ProductCategory.homeGarden:
        return 'Home & Garden';
      case ProductCategory.sportsOutdoors:
        return 'Sports & Outdoors';
      case ProductCategory.toysGames:
        return 'Toys & Games';
      case ProductCategory.healthBeauty:
        return 'Health & Beauty';
      case ProductCategory.automotive:
        return 'Automotive';
      case ProductCategory.food:
        return 'Food';
      case ProductCategory.other:
        return 'Other';
    }
  }
}