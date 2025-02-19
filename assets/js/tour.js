class ZellowTour {
    constructor() {
        // Fix the scope binding for methods
        this.tour = new Shepherd.Tour({
            defaultStepOptions: {
                cancelIcon: {
                    enabled: true
                },
                classes: 'shepherd-theme-custom shepherd-spotlight',
                scrollTo: { behavior: 'smooth', block: 'center' },
                modalOverlayOpeningRadius: 4
            },
            useModalOverlay: true
        });

        // Bind methods to this instance
        this.start = this.start.bind(this);
        this.getCurrentPage = this.getCurrentPage.bind(this);
        this.isCurrentPage = this.isCurrentPage.bind(this);

        this.initTourSteps();
    }

    initTourSteps() {
        // Define button actions with proper binding
        const commonButtons = {
            back: {
                text: 'Back',
                action: () => this.tour.back()
            },
            next: {
                text: 'Next',
                action: () => this.tour.next()
            }
        };

        // Navigation tour steps
        const navigationSteps = [
            {
                id: 'nav-home',
                title: 'Dashboard',
                text: 'Your main dashboard with key business metrics.',
                attachTo: { element: 'a[href="index.php"]', on: 'right' }
            },
            {
                id: 'nav-products',
                title: 'Products',
                text: 'Manage your product catalog and inventory items.',
                attachTo: { element: 'a[href="products.php"]', on: 'right' }
            },
            {
                id: 'nav-categories',
                title: 'Categories',
                text: 'Organize products into categories.',
                attachTo: { element: 'a[href="categories.php"]', on: 'right' }
            },
            {
                id: 'nav-inventory',
                title: 'Inventory',
                text: 'Track stock levels and manage inventory.',
                attachTo: { element: 'a[href="inventory.php"]', on: 'right' }
            },
            {
                id: 'nav-orders',
                title: 'Orders',
                text: 'View and manage customer orders.',
                attachTo: { element: 'a[href="orders.php"]', on: 'right' }
            },
            {
                id: 'nav-dispatch',
                title: 'Dispatch',
                text: 'Handle order fulfillment and shipping.',
                attachTo: { element: 'a[href="dispatch.php"]', on: 'right' }
            },
            {
                id: 'nav-notifications',
                title: 'Notifications',
                text: 'Check system alerts and messages.',
                attachTo: { element: 'a[href="notifications.php"]', on: 'right' }
            },
            {
                id: 'nav-promotions',
                title: 'Promotions',
                text: 'Manage sales and promotional campaigns.',
                attachTo: { element: 'a[href="promotions.php"]', on: 'right' }
            },
            {
                id: 'nav-analytics',
                title: 'Analytics',
                text: 'View detailed business reports and insights.',
                attachTo: { element: 'a[href="analytics.php"]', on: 'right' }
            }
        ];

        // Dashboard-specific steps for index page
        const dashboardSteps = {
            index: [
                {
                    id: 'orders-summary',
                    title: 'Total Orders',
                    text: 'Shows your total order count and recent order activity.',
                    attachTo: { element: '.col-md-3:nth-child(1)', on: 'bottom' }
                },
                {
                    id: 'customers-summary',
                    title: 'Active Customers',
                    text: 'Track your active customer base.',
                    attachTo: { element: '.col-md-3:nth-child(2)', on: 'bottom' }
                },
                {
                    id: 'inventory-summary',
                    title: 'Inventory Overview',
                    text: 'Quick view of your total stock levels.',
                    attachTo: { element: '.col-md-3:nth-child(3)', on: 'bottom' }
                },
                {
                    id: 'services-summary',
                    title: 'Manage Services',
                    text: 'Add and update the services in your offerings.',
                    attachTo: { element: '.col-md-3:nth-child(5)', on: 'bottom' }
                },
                {
                    id: 'suppliers-summary',
                    title: 'Manage Suppliers',
                    text: 'Add and update the suppliers in your network.',
                    attachTo: { element: '.col-md-3:nth-child(6)', on: 'bottom' }
                },
                    
                {
                    id: 'transactions-summary',
                    title: 'Transactions',
                    text: 'View recent transactions and payment activity.',
                    attachTo: { element: '.col-md-3:nth-child(7)', on: 'bottom' }
                    
                },
                {
                    id: 'quick-actions',
                    title: 'Quick Actions',
                    text: 'Frequently used functions for easy access.',
                    attachTo: { element: '.my-3', on: 'bottom' }
                }
            ]
        };

        // Combine steps based on current page
        const currentPage = this.getCurrentPage();
        const steps = [
            {
                id: 'welcome',
                title: 'Welcome to Zellow Admin',
                text: 'Let\'s take a tour of the system.',
                attachTo: { element: '.navbar', on: 'bottom' },
                buttons: [
                    { 
                        text: 'Skip',
                        action: () => this.tour.complete(),
                        classes: 'shepherd-button-secondary'
                    },
                    {
                        text: 'Start',
                        action: () => this.tour.next()
                    }
                ]
            },
            ...navigationSteps,
            ...(dashboardSteps[currentPage] || []),
            {
                id: 'help',
                title: 'Help & Support',
                text: 'Click here anytime to restart this tour.',
                attachTo: { element: '#help-trigger', on: 'right' },
                buttons: [
                    { 
                        text: 'Back',
                        action: () => this.tour.back()
                    },
                    {
                        text: 'Finish',
                        action: () => this.tour.complete()
                    }
                ]
            }
        ];

        // Add common buttons to all steps except first and last
        steps.forEach((step, index) => {
            if (index !== 0 && index !== steps.length - 1) {
                step.buttons = [
                    { 
                        text: 'Back',
                        action: () => this.tour.back()
                    },
                    {
                        text: 'Next',
                        action: () => this.tour.next()
                    }
                ];
            }
            this.tour.addStep(step);
        });
    }

    getCurrentPage() {
        const path = window.location.pathname;
        const page = path.split('/').pop().replace('.php', '');
        return page || 'index';
    }

    isCurrentPage(pageName) {
        return this.getCurrentPage() === pageName.replace('.php', '');
    }

    start() {
        // Ensure tour exists and has steps
        if (!this.tour || !this.tour.steps || !this.tour.steps.length) {
            console.error('Tour not properly initialized');
            return;
        }

        // Check for visible elements
        const visibleSteps = this.tour.steps.filter(step => {
            const element = document.querySelector(step.options.attachTo.element);
            return element && element.offsetParent !== null;
        });

        if (!visibleSteps.length) {
            console.warn('No visible elements found for tour');
            return;
        }

        this.tour.start();
    }
}
