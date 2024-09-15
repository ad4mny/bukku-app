### Documentation for Laravel RESTful API (JWT Auth + Purchase/Sale Transactions)

---

### **Steps to Set Up the Project**

1. **Install Dependencies**:  
   Run the following command to install all necessary packages after cloning the project:

    ```bash
    composer install
    ```

2. **Set up `.env`**:  
   Copy the `.env.example` file to `.env` and configure your database credentials:

    ```bash
    cp .env.example .env
    ```

3. **Run Migrations**:  
   Create the database tables by running:

    ```bash
    php artisan migrate
    ```

4. **Seed the Products Table**:  
   Insert sample data into the `products` table using the `ProductSeeder`:

    ```bash
    php artisan db:seed --class=ProductSeeder
    ```

5. **Generate JWT Secret Key**:  
   The app uses JWT for authentication, so generate the secret key for JWT:

    ```bash
    php artisan jwt:secret
    ```

6. **Run the Application**:  
   Start the Laravel development server:
    ```bash
    php artisan serve
    ```

7. **Run Test**:  
   Run test cases by running:

    ```bash
    php artisan test
    ```
---

### **API Routes**

The API has been developed using Laravel with JWT-based authentication and follows RESTful principles.

#### **Auth Routes**:

1. **Register a New User**:

    ```http
    POST /api/register
    ```

    - **Request Body**:
        ```json
        {
            "name": "John Doe",
            "email": "john@example.com",
            "password": "password123"
        }
        ```
    - **Response**:
        - **Success**:
            ```json
            {
                "message": "User successfully registered",
                "token": "JWT_TOKEN"
            }
            ```
        - **Failure**: Validation errors or if email already exists.

2. **Login**:

    ```http
    POST /api/login
    ```

    - **Request Body**:
        ```json
        {
            "email": "john@example.com",
            "password": "password123"
        }
        ```
    - **Response**:
        - **Success**:
            ```json
            {
                "message": "Login successful",
                "token": "JWT_TOKEN"
            }
            ```
        - **Failure**: Invalid credentials or missing fields.

3. **Logout** (Authenticated):
    ```http
    POST /api/logout
    ```
    - Requires the **JWT token** in the `Authorization` header.
    - **Response**:
        ```json
        {
            "message": "User successfully logged out"
        }
        ```

---

#### **Transaction Routes** (Authenticated):

All transaction-related routes require the user to be authenticated (JWT token).

1. **Record a New Transaction**:

    ```http
    POST /api/transactions
    ```

    - **Request Body**:
        ```json
        {
            "product_id": 1,
            "quantity": 10,
            "price": 2.0,
            "type": "purchase", // or "sale"
            "transaction_date": "2024-09-14" // Optional
        }
        ```
    - **Description**:
        - **Purchase**: Adds new inventory and updates cost.
        - **Sale**: Deducts inventory, calculates the **Cost of Goods Sold (COGS)** based on the **Weighted Average Cost (WAC)**, and records the sale.
    - **Response**:
        ```json
        {
            "message": "Transaction recorded successfully"
        }
        ```

2. **List All Transactions**:

    ```http
    GET /api/transactions
    ```

    - **Optional Query Parameters**:
        - `type`: Filter by transaction type (either "purchase" or "sale").
    - **Response**:
        - Example:
            ```json
            [
              {
                "id": 1,
                "product_id": 1,
                "quantity": 10,
                "price": 2.00,
                "type": "purchase",
                "transaction_date": "2024-09-14",
                "created_at": "2024-09-14T08:00:00.000Z",
                "updated_at": "2024-09-14T08:00:00.000Z"
              },
              ...
            ]
            ```

3. **Update a Transaction**:

    ```http
    PUT /api/transactions/{id}
    ```

    - **Request Body**:
        ```json
        {
            "quantity": 15,
            "price": 2.5,
            "transaction_date": "2024-09-15"
        }
        ```
    - **Response**:
        ```json
        {
            "message": "Transaction updated successfully"
        }
        ```

4. **Delete a Transaction**:
    ```http
    DELETE /api/transactions/{id}
    ```
    - **Response**:
        ```json
        {
            "message": "Transaction deleted successfully"
        }
        ```

---

### **Middleware**

-   **JWT Authentication**: All routes under `auth:api` middleware require a valid JWT token to access.

---

### **Tables & Data Structure**

1. **Users Table**:

    - Stores user information (used for registration, login, etc.).

2. **Products Table**:

    - Seeded with sample data using `ProductSeeder`. Each product has a unique `id`, `name`, `total_quantity`, and `total_value`.

3. **Transactions Table**:

    - Stores transaction data for both purchases and sales.
    - Fields: `id`, `user_id`, `product_id`, `quantity`, `price`, `transaction_date`, `type` (either "purchase" or "sale").

4. **Costings Table**:
    - Tracks the total inventory quantity and the **Weighted Average Cost (WAC)** for each product.
    - Fields: `id`, `product_id`, `quantity`, `total_value`, `unit_cost`.

---

### **Logic Summary**

-   **Weighted Average Cost (WAC)**: The `costings` table updates with each purchase to calculate and store the WAC for each product.
-   **Cost of Goods Sold (COGS)**: For sales, COGS is calculated based on the current WAC from the `costings` table.
-   **Date Adjustments**: Transaction dates are adjusted dynamically to maintain sequence when a new transaction is inserted with a date that affects the existing sequence.

---

### **JWT Token Management**

-   Upon successful **login**, a JWT token is returned, which should be included in all subsequent requests as a Bearer token:

    -   Example:
        ```
        Authorization: Bearer JWT_TOKEN
        ```

-   **Logout**: To invalidate the token, simply call the `logout` endpoint.

---

### **Error Handling**

-   **Validation Errors**: If any validation fails (e.g., missing fields), an appropriate error message will be returned with a 422 status.
-   **Unauthorized Access**: If a request is made to a protected route without a valid JWT token, a 401 Unauthorized response is returned.
