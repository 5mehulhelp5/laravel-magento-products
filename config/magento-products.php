<?php

return [
    /** Job Queue */
    'queue' => 'default',

    /** Interval in hours which the product data should re-downloaded */
    'check_interval' => 24,

    /** Page size of products to retrieve from Magento */
    'page_size' => 100,

    /** Maximum percentage of products that may be deleted. Set to null to disable */
    'deletion_threshold' => 0.1,
];
