<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Product Details - Macaron</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
    .nav-item, .nav-link {
      color: #ff6b6b !important;
    }
    .nav-item:hover, .nav-link:hover {
      color: rgb(70, 70, 70) !important;
    }
  </style>
</head>

<body class="bg-light d-flex min-vh-100">

    <div class="flex-grow-1 d-flex flex-column">

        <!-- Top Header Bar -->
        <header class="bg-danger bg-gradient text-white d-flex justify-content-between align-items-center px-4 py-3 rounded-bottom">
            <h1 class="h4 mb-0">Product Details</h1>
            <div class="d-flex gap-3 fs-4">
                <span class="material-icons" role="button" tabindex="0" style="cursor:pointer;">notifications</span>
                <span class="material-icons" role="button" tabindex="0" style="cursor:pointer;">shopping_cart</span>
            </div>
        </header>

        <!-- Main Content -->
        <main class="p-4 bg-white flex-grow-1 overflow-auto">

            <div class="row gy-4">

                <!-- Product Image -->
                <div class="col-md-6">
                    <img src="macaron.jpg" alt="Macaron Product" class="img-fluid rounded shadow" style="height: 400px; object-fit: cover; width: 100%;" />
                </div>

                <!-- Product Info -->
                <div class="col-md-6 bg-light rounded p-4">
                    <h2 class="text-danger fw-bold mb-4">Macaron</h2>

                    <div class="mb-3 d-flex">
                        <div class="fw-semibold text-secondary" style="min-width: 140px;">Brand:</div>
                        <div>ESTA BETTERU CO</div>
                    </div>
                    <div class="mb-3 d-flex">
                        <div class="fw-semibold text-secondary" style="min-width: 140px;">Flavour:</div>
                        <div>9 different flavours</div>
                    </div>
                    <div class="mb-3 d-flex">
                        <div class="fw-semibold text-secondary" style="min-width: 140px;">Diet Type:</div>
                        <div>Vegan</div>
                    </div>
                    <div class="mb-3 d-flex">
                        <div class="fw-semibold text-secondary" style="min-width: 140px;">Weight:</div>
                        <div>5 Guans</div>
                    </div>
                    <div class="mb-3 d-flex">
                        <div class="fw-semibold text-secondary" style="min-width: 140px;">Specialty:</div>
                        <div>Gluten Free, Sugar Free</div>
                    </div>
                    <div class="mb-3 d-flex">
                        <div class="fw-semibold text-secondary" style="min-width: 140px;">Info:</div>
                        <div>Egg Free, Allergen-Free</div>
                    </div>
                    <div class="mb-3 d-flex">
                        <div class="fw-semibold text-secondary" style="min-width: 140px;">Items:</div>
                        <div>1</div>
                    </div>

                    <div class="d-flex gap-3 mt-4 flex-wrap">
                        <button class="btn btn-danger flex-grow-1 flex-md-grow-0" style="min-width: 120px;">Delete</button>
                        <button class="btn btn-danger flex-grow-1 flex-md-grow-0" style="min-width: 120px;">Update</button>
                    </div>
                </div>

                <!-- Description Section -->
                <section class="col-12 bg-light rounded p-4">
                    <h3 class="text-danger border-bottom border-2 border-danger pb-2 mb-3">Description</h3>
                    <p class="text-secondary lh-base fs-6">
                        Lorem ipsum dolor sit amet consectetur adipisicing elit. Error in vere sapiente odio,
                        error dolore vero temporibus consequatur, nobis veniam odit dignissimos consectetur
                        quae in perferendis doloribusdellus corporis, eoges dictus, repetet amet.Lorem ipsum dolor sit amet consectetur adipisicing elit. Error in vere sapiente odio,
                        error dolore vero temporibus consequatur, nobis veniam odit dignissimos consectetur
                        quae in perferendis doloribusdellus corporis, eoges dictus, repetet amet.
                    </p>
                </section>

                <!-- Ingredients Section -->
                <section class="col-12 bg-light rounded p-4">
                    <h3 class="text-danger border-bottom border-2 border-danger pb-2 mb-3">Ingredients</h3>
                    <p class="text-secondary lh-base fs-6">
                        Almond flour, powdered sugar, egg whites, natural food coloring, vegan butter,
                        organic vanilla extract. Allergen-free ingredients, no artificial preservatives.
                        Lorem ipsum dolor sit amet consectetur adipisicing elit. Quisquam voluptatum
                        voluptate, quibusdam.
                    </p>
                </section>

                <!-- Reviews Section -->
                <section class="col-12">
                    <h3 class="text-danger border-bottom border-2 border-danger pb-2 mb-4">Reviews</h3>

                    <div class="d-flex flex-column gap-3">

                        <div class="d-flex gap-3 p-3 bg-white rounded border border-danger shadow-sm">
                            <img src="https://i.pravatar.cc/45?img=1" alt="User avatar" class="rounded-circle" width="45" height="45" />
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold text-dark">Lara Smith</span>
                                    <small class="text-muted">2023-08-15</small>
                                </div>
                                <div class="text-warning mb-2" aria-label="5 stars">★★★★★</div>
                                <p class="text-secondary mb-0 lh-sm">
                                    Absolutely delicious! Perfect balance of sweetness and texture.
                                </p>
                            </div>
                        </div>

                        <div class="d-flex gap-3 p-3 bg-white rounded border border-danger shadow-sm">
                            <img src="https://i.pravatar.cc/45?img=5" alt="User avatar" class="rounded-circle" width="45" height="45" />
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold text-dark">Ram Aoki</span>
                                    <small class="text-muted">2023-05-22</small>
                                </div>
                                <div class="text-warning mb-2" aria-label="4 stars">★★★★☆</div>
                                <p class="text-secondary mb-0 lh-sm">
                                    Good quality, though slightly too sweet for my taste.
                                </p>
                            </div>
                        </div>

                        <div class="d-flex gap-3 p-3 bg-white rounded border border-danger shadow-sm">
                            <img src="https://i.pravatar.cc/45?img=7" alt="User avatar" class="rounded-circle" width="45" height="45" />
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold text-dark">Marka Lee</span>
                                    <small class="text-muted">2023-04-18</small>
                                </div>
                                <div class="text-warning mb-2" aria-label="3 stars">★★★☆☆</div>
                                <p class="text-secondary mb-0 lh-sm">
                                    Tasty but a bit pricey for the quantity.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </main>
    </div>
</body>