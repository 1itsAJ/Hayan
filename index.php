<?php
// --- DYNAMIC GALLERY GENERATOR ---
// This script automatically scans your folders and builds the gallery items.
function getGalleryItems() {
    $items = [];
    $baseDir = __DIR__;

    // 1. Scan Main Decades (1970 - 2020)
    $decades = ['1970', '1980', '1990', '2000', '2010', '2020'];
    foreach ($decades as $decade) {
        $decadePath = $baseDir . DIRECTORY_SEPARATOR . $decade;
        if (is_dir($decadePath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($decadePath));
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile() && in_array(strtolower($fileInfo->getExtension()), ['jpg', 'jpeg', 'png', 'gif'])) {
                    $path = $fileInfo->getPathname();
                    
                    // Determine category based on folder name
                    $catStr = strtolower(basename(dirname($path)));
                    $category = 'painting'; // default
                    if (strpos($catStr, 'print') !== false) $category = 'Printmaking';
                    if (strpos($catStr, 'paper') !== false) $category = 'on-paper';

                    // Format title from filename
                    $title = ucwords(str_replace(['-', '_'], ' ', pathinfo($fileInfo->getFilename(), PATHINFO_FILENAME)));

                    // Extract raw text from matching .txt file
                    $rawText = '';
                    $txtPath = pathinfo($path, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . pathinfo($path, PATHINFO_FILENAME) . '.txt';
                    
                    if (file_exists($txtPath)) {
                        $rawText = trim(file_get_contents($txtPath));
                    }
                    
                    // Smart Defaults if text file is missing or empty
                    if (empty($rawText)) {
                        if ($category === 'Printmaking') $rawText = 'Etching';
                        elseif ($category === 'painting') $rawText = 'Oil on Canvas';
                        elseif ($category === 'on-paper') $rawText = 'Mixed Media on Paper';
                    }

                    // Get relative path for web URL
                    $relativePath = substr($path, strlen($baseDir) + 1);
                    $webUrl = str_replace('\\', '/', $relativePath);
                    
                    // Encode URL to handle spaces
                    $encodedUrl = implode('/', array_map('rawurlencode', explode('/', $webUrl)));

                    $items[] = [
                        'url' => $encodedUrl,
                        'title' => $title,
                        'category' => $category,
                        'subcat' => $decade,
                        'raw_text' => $rawText
                    ];
                }
            }
        }
    }

    // 2. Scan ART-BOOK
    $artBookPath = $baseDir . DIRECTORY_SEPARATOR . 'hayan C-V & prass' . DIRECTORY_SEPARATOR . 'ART-BOOK';
    if (is_dir($artBookPath)) {
        $bookImagesMap = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($artBookPath));
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && in_array(strtolower($fileInfo->getExtension()), ['jpg', 'jpeg', 'png'])) {
                $dir = dirname($fileInfo->getPathname());
                $bookImagesMap[$dir][] = $fileInfo->getPathname();
            }
        }

        uksort($bookImagesMap, 'strnatcasecmp');

        foreach ($bookImagesMap as $dir => $images) {
            $bookFolderName = basename($dir);
            
            $subcat = '2004'; // fallback
            if (preg_match('/(2004|2010|2012|2020)/', $dir, $matches)) {
                $subcat = $matches[1];
            }
            
            $cleanName = preg_replace('/-\d{4}$/', '', $bookFolderName);
            $cleanName = preg_replace('/^ART-BOOK\s*-?/i', '', $cleanName);
            $bookTitle = ucwords(str_replace(['-', '_'], ' ', $cleanName));

            usort($images, function($a, $b) {
                return strnatcasecmp(basename($a), basename($b));
            });

            $pages = [];
            $coverIndex = -1;

            foreach ($images as $index => $path) {
                $relativePath = substr($path, strlen($baseDir) + 1);
                $webUrl = str_replace('\\', '/', $relativePath);
                $encodedUrl = implode('/', array_map('rawurlencode', explode('/', $webUrl)));
                $pages[] = $encodedUrl;
                
                if (strpos(strtolower(basename($path)), 'cover.') === 0) {
                    $coverIndex = $index;
                }
            }

            if ($coverIndex !== -1) {
                $coverImage = $pages[$coverIndex]; 
                unset($pages[$coverIndex]); 
                array_unshift($pages, $coverImage); 
                $pages = array_values($pages);
            } elseif (count($pages) > 0) {
                $coverImage = $pages[0];
            } else {
                $coverImage = '';
            }

            if ($coverImage) {
                $items[] = [
                    'url' => $coverImage,
                    'title' => $bookTitle,
                    'category' => 'art-book',
                    'subcat' => $subcat,
                    'is_book' => true,
                    'book_contents' => json_encode($pages),
                    'raw_text' => 'Art Book Collection'
                ];
            }
        }
    }

    // 3. Scan Portfolio
    $portfolioPath = $baseDir . DIRECTORY_SEPARATOR . 'hayan C-V & prass' . DIRECTORY_SEPARATOR . 'Portfolio Box Work\'s';
    if (is_dir($portfolioPath)) {
        $portfolioImagesMap = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($portfolioPath));
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && in_array(strtolower($fileInfo->getExtension()), ['jpg', 'jpeg', 'png'])) {
                $dir = dirname($fileInfo->getPathname());
                $portfolioImagesMap[$dir][] = $fileInfo->getPathname();
            }
        }

        uksort($portfolioImagesMap, 'strnatcasecmp');

        foreach ($portfolioImagesMap as $dir => $images) {
            $portfolioFolderName = basename($dir);
            
            if (preg_match('/^Portfolio Box Work\'s \d{4}$/i', $portfolioFolderName)) {
                continue; 
            }

            $subcat = '1990'; 
            if (preg_match('/(1990|2000|2010|2020)/', $dir, $matches)) {
                $subcat = $matches[1];
            }
            
            $cleanName = preg_replace('/-\d{4}$/', '', $portfolioFolderName); 
            $cleanName = preg_replace('/\(.*?\)/', '', $cleanName); 
            $cleanName = str_replace(['-', '_'], ' ', $cleanName); 
            $portfolioTitle = trim(ucwords(strtolower($cleanName)));

            usort($images, function($a, $b) {
                return strnatcasecmp(basename($a), basename($b));
            });

            $pages = [];
            $coverIndex = -1;

            foreach ($images as $index => $path) {
                $relativePath = substr($path, strlen($baseDir) + 1);
                $webUrl = str_replace('\\', '/', $relativePath);
                $encodedUrl = implode('/', array_map('rawurlencode', explode('/', $webUrl)));
                $pages[] = $encodedUrl;
                
                if (strpos(strtolower(basename($path)), 'cover.') === 0) {
                    $coverIndex = $index;
                }
            }

            if ($coverIndex !== -1) {
                $coverImage = $pages[$coverIndex]; 
                unset($pages[$coverIndex]); 
                array_unshift($pages, $coverImage); 
                $pages = array_values($pages);
            } elseif (count($pages) > 0) {
                $coverImage = $pages[0];
            } else {
                $coverImage = '';
            }

            if ($coverImage) {
                $items[] = [
                    'url' => $coverImage,
                    'title' => $portfolioTitle,
                    'category' => 'portfolio',
                    'subcat' => $subcat,
                    'is_book' => true, 
                    'book_contents' => json_encode($pages),
                    'raw_text' => 'Portfolio Collection'
                ];
            }
        }
    }

    return $items;
}

$galleryItems = getGalleryItems();

// --- FALLBACK MOCK DATA ---
if (empty($galleryItems)) {
    $galleryItems = [
        ['url' => 'https://images.unsplash.com/photo-1542038784456-1ea8e935640e?q=80&w=800', 'title' => 'Adam is waiting to eat the apple', 'category' => 'Printmaking', 'subcat' => '1970', 'raw_text' => "Etching\n20.0 X 25.0 CM"],
        ['url' => 'https://images.unsplash.com/photo-1579783902614-a3fb3927b6a5?q=80&w=800', 'title' => 'Bloody Dramatic Scene', 'category' => 'painting', 'subcat' => '1980', 'raw_text' => "Oil on Canvas\n100 X 120 CM"],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hayan Art | Artist Portfolio</title>
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;800&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        serif: ['Playfair Display', 'serif'],
                    },
                    colors: {
                        dark: '#121212',
                        brand: '#2c2b29'
                    }
                }
            }
        }
    </script>
    <style>
        html { scroll-behavior: smooth; }
        .gallery-item { transition: all 0.4s ease-in-out; }
        
        .gallery-item.hidden-item {
            display: none !important;
        }

        .gallery-item.show-item {
            animation: popIn 0.4s ease-out forwards;
        }

        @keyframes popIn {
            0% { opacity: 0; transform: scale(0.95); }
            100% { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body class="bg-[#EAE4D9] text-gray-900 antialiased font-sans">

    <nav class="fixed w-full top-0 z-50 bg-[#EAE4D9]/80 backdrop-blur-md border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex-shrink-0 flex items-center">
                    <a href="#" class="text-2xl font-serif font-semibold tracking-wide">Hayan Art</a>
                </div>
                <div class="hidden md:flex space-x-8 items-center">
                    <a href="#home" class="text-gray-600 hover:text-dark transition-colors font-medium">Home</a>
                    
                    <div class="relative group">
                        <button class="text-gray-600 group-hover:text-dark transition-colors font-medium flex items-center gap-1 focus:outline-none">
                            Artworks
                            <svg class="w-4 h-4 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div class="absolute left-0 mt-2 w-48 bg-[#EAE4D9] border border-gray-200 rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform origin-top-left -translate-y-2 group-hover:translate-y-0">
                            <div class="py-1">
                                <a href="#portfolio" onclick="document.querySelector('[data-filter=\'Printmaking\']').click()" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-dark">Printmaking</a>
                                <a href="#portfolio" onclick="document.querySelector('[data-filter=\'painting\']').click()" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-dark">Painting</a>
                                <a href="#portfolio" onclick="document.querySelector('[data-filter=\'on-paper\']').click()" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-dark">On Paper</a>
                            </div>
                        </div>
                    </div>

                    <div class="relative group">
                        <button class="text-gray-600 group-hover:text-dark transition-colors font-medium flex items-center gap-1 focus:outline-none">
                            Collections
                            <svg class="w-4 h-4 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div class="absolute left-0 mt-2 w-48 bg-[#EAE4D9] border border-gray-200 rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform origin-top-left -translate-y-2 group-hover:translate-y-0">
                            <div class="py-1">
                                <a href="#portfolio" onclick="document.querySelector('[data-filter=\'art-book\']').click()" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-dark">Art Book</a>
                                <a href="#portfolio" onclick="document.querySelector('[data-filter=\'portfolio\']').click()" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-dark">Portfolio</a>
                            </div>
                        </div>
                    </div>

                    <a href="cv.html" class="text-gray-600 hover:text-dark transition-colors font-medium">CV</a>
                </div>
            </div>
        </div>
    </nav>

    <section id="home" class="pt-20 w-full min-h-[90vh] flex flex-col md:flex-row bg-brand">
        <div class="w-full md:w-1/2 relative min-h-[50vh] md:min-h-full">
            <img src="1970/Painting/doleful man.jpg" onerror="this.src='https://images.unsplash.com/photo-1579783902614-a3fb3927b6a5?q=80&w=1200&auto=format&fit=crop'" class="absolute inset-0 w-full h-full object-cover object-top" alt="Hayan Art Painting">
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent"></div>
            
            <div class="absolute bottom-8 left-8 md:bottom-16 md:left-12 text-white">
                <h1 class="text-5xl md:text-7xl font-serif mb-3 tracking-wide">Hayan Art</h1>
                <p class="text-lg md:text-xl font-light text-gray-200">Fine Art Painter | Original Oil Paintings</p>
            </div>
        </div>

        <div class="w-full md:w-1/2 bg-brand flex items-center justify-center p-12 md:p-24 text-gray-100 relative shadow-[inset_10px_0_20px_rgba(0,0,0,0.5)]">
            <div class="absolute inset-0 opacity-[0.15]" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 200 200%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noiseFilter%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.85%22 numOctaves=%223%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noiseFilter)%22/%3E%3C/svg%3E');"></div>
            
            <div class="relative z-10 max-w-lg">
                <p class="text-xl md:text-2xl font-serif leading-relaxed md:leading-loose text-gray-300">
                    For over 30 years, Hayan has explored themes of heritage, nature, and emotion through oil and acrylic paintings. His work has been exhibited in numerous galleries and private collections...
                </p>
            </div>
        </div>
    </section>

    <section id="portfolio" class="py-20 px-4 max-w-[90rem] mx-auto min-h-screen">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-serif font-bold text-dark mb-8">Selected Works</h2>
            
            <div class="flex flex-wrap justify-center gap-3 md:gap-4 mb-4" id="filter-buttons">
                <button class="filter-btn px-6 py-2 rounded-full border border-dark bg-dark text-white font-medium transition-all" data-filter="Printmaking">Printmaking</button>
                <button class="filter-btn px-6 py-2 rounded-full border border-gray-300 bg-transparent text-gray-600 hover:border-dark hover:text-dark font-medium transition-all" data-filter="painting">Painting</button>
                <button class="filter-btn px-6 py-2 rounded-full border border-gray-300 bg-transparent text-gray-600 hover:border-dark hover:text-dark font-medium transition-all" data-filter="on-paper">On Paper</button>
                <button class="filter-btn px-6 py-2 rounded-full border border-gray-300 bg-transparent text-gray-600 hover:border-dark hover:text-dark font-medium transition-all" data-filter="art-book">Art Book</button>
                <button class="filter-btn px-6 py-2 rounded-full border border-gray-300 bg-transparent text-gray-600 hover:border-dark hover:text-dark font-medium transition-all" data-filter="portfolio">Portfolio</button>
            </div>

            <div class="hidden flex-wrap justify-center gap-2 md:gap-3 mb-8 transition-all duration-300" id="sub-filter-buttons"></div>
            
            <div id="book-view-header" class="hidden flex-col items-center mb-8 transition-all duration-300">
                <button id="back-to-books-btn" class="mb-4 px-5 py-2 rounded-full border border-gray-300 bg-[#EAE4D9] text-gray-700 hover:border-dark hover:text-dark hover:shadow-md flex items-center gap-2 font-medium transition-all focus:outline-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to <span id="back-to-year" class="font-bold"></span> <span id="back-to-type">Books</span>
                </button>
                <h3 id="book-view-title" class="text-2xl md:text-3xl font-serif font-bold text-dark"></h3>
            </div>
        </div>

        <div class="columns-1 sm:columns-2 lg:columns-3 xl:columns-4 gap-4 md:gap-6 relative" id="gallery-grid">
            <?php foreach ($galleryItems as $item): ?>
                <div class="gallery-item group relative break-inside-avoid mb-4 md:mb-6 inline-block w-full overflow-hidden rounded-md bg-gray-200 show-item shadow-sm hover:shadow-xl cursor-pointer <?php echo isset($item['is_book']) ? 'book-trigger' : 'lightbox-trigger'; ?>" 
                     data-category="<?php echo htmlspecialchars($item['category']); ?>" 
                     data-subcat="<?php echo htmlspecialchars($item['subcat']); ?>"
                     data-title="<?php echo htmlspecialchars($item['title']); ?>"
                     data-info="<?php echo htmlspecialchars($item['raw_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                     <?php if(isset($item['is_book'])) echo "data-book-contents='" . htmlspecialchars($item['book_contents'], ENT_QUOTES, 'UTF-8') . "'"; ?>
                     <?php if(isset($item['is_book'])) echo "data-book-title='" . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . "'"; ?>
                     >
                    
                    <img src="<?php echo $item['url']; ?>" 
                         alt="<?php echo htmlspecialchars($item['title']); ?>" 
                         class="w-full h-auto block transition-transform duration-700 group-hover:scale-105"
                         loading="lazy">
                    
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-6">
                        <span class="text-gray-300 text-xs font-semibold tracking-wider uppercase mb-1">
                            <?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $item['category']))) . ' • ' . htmlspecialchars($item['subcat']); ?>
                        </span>
                        <h3 class="text-white text-lg font-serif font-bold">
                            <?php echo htmlspecialchars($item['title']); ?>
                            <?php if(isset($item['is_book'])): ?>
                                <span class="block mt-1 text-sm font-sans font-normal text-gray-300 group-hover:text-white transition-colors">
                                    <?php echo $item['category'] === 'portfolio' ? 'View Portfolio →' : 'View Book →'; ?>
                                </span>
                            <?php endif; ?>
                        </h3>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ELEGANT LIGHTBOX -->
    <div id="lightbox" class="fixed inset-0 z-[100] bg-[#EAE4D9] hidden opacity-0 transition-opacity duration-300 overflow-y-auto">
        
        <button id="lightbox-close" class="fixed top-6 right-8 md:top-10 md:right-12 text-gray-500 hover:text-black focus:outline-none z-[101] transition-colors bg-[#EAE4D9]/80 rounded-full p-2">
            <svg class="w-10 h-10 md:w-12 md:h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div class="min-h-screen flex flex-col md:flex-row w-full max-w-[90rem] mx-auto px-6 md:px-16 items-center justify-center gap-10 md:gap-24 relative z-10 py-24 md:py-12">
            
            <div class="w-full md:w-1/2 flex justify-center md:justify-end items-center">
                <img id="lightbox-img" src="" alt="Enlarged Art" class="max-w-full max-h-[60vh] md:max-h-[85vh] object-contain shadow-2xl bg-[#EAE4D9]">
            </div>
            
            <div class="w-full md:w-1/2 flex flex-col justify-center text-left">
                <!-- Title without the year -->
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-serif text-dark mb-4 md:mb-6">
                    <span id="lightbox-title">Artwork Title</span>
                </h2>
                
                <!-- Raw text from text file with pre-wrap for line breaks -->
                <div id="lightbox-info" class="font-sans text-[14px] md:text-[15px] text-gray-600 tracking-wider uppercase mb-10 md:mb-16 whitespace-pre-wrap leading-relaxed"></div>

                <div class="flex items-center justify-start gap-8 md:gap-16 text-[14px] md:text-[15px] font-sans font-semibold text-gray-800">
                    <button id="lightbox-prev" class="hover:text-black transition-colors flex items-center gap-2 focus:outline-none disabled:opacity-30 disabled:cursor-not-allowed uppercase tracking-wider py-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
                        Previous
                    </button>
                    <button id="lightbox-next" class="hover:text-black transition-colors flex items-center gap-2 focus:outline-none disabled:opacity-30 disabled:cursor-not-allowed uppercase tracking-wider py-2">
                        Next 
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <footer id="contact" class="bg-[#EAE4D9] pt-16 pb-8 text-[#1a1a1a] relative border-t border-gray-300">
        <div class="absolute inset-0 opacity-[0.04] pointer-events-none" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 200 200%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noiseFilter%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.85%22 numOctaves=%223%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noiseFilter)%22/%3E%3C/svg%3E');"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="flex flex-col md:flex-row justify-between items-start mb-16">
                <div class="w-full md:w-1/3 mb-10 md:mb-0">
                    <div class="mb-2">
                        <svg width="40" height="24" viewBox="0 0 40 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="12" fill="black"/>
                            <path d="M26 0V24C32.6274 24 38 18.6274 38 12C38 5.37258 32.6274 0 26 0Z" fill="black"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-sans font-medium tracking-wide">Hayan Art</h2>
                </div>

                <div class="w-full md:w-2/3 flex flex-col sm:flex-row justify-between md:justify-end md:gap-24 font-sans text-[15px] leading-relaxed">
                    <div class="flex flex-col gap-1.5 mb-6 sm:mb-0">
                        <a href="index.php#home" class="hover:text-black hover:underline transition-all">Home</a>
                        <a href="cv.html" class="hover:text-black hover:underline transition-all">About</a>
                        <a href="mailto:hello@hayan.art" class="hover:text-black hover:underline transition-all">Contact</a>
                    </div>
                    <div class="flex flex-col gap-1.5 mb-6 sm:mb-0">
                        <a href="https://www.facebook.com/hayan.abduljabbar" class="hover:text-black hover:underline transition-all" target="_blank">Facebook</a>
                        <a href="#" class="hover:text-black hover:underline transition-all">Twitter</a>
                        <a href="#" class="hover:text-black hover:underline transition-all">LinkedIn</a>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <p>Tel. +964 770 392 6787</p>
                        <p>Baghdad, Iraq</p>
                    </div>
                </div>
            </div>

            <div class="relative flex flex-col md:flex-row items-center justify-center text-[13px] md:text-sm mt-12 pt-8">
                <p class="md:absolute md:left-0 mb-4 md:mb-0">Proudly designed by <a href="#" class="underline hover:text-black font-medium">Shams Hayan</a></p>
                <p>&copy; 2026 Hayan. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const subFilterContainer = document.getElementById('sub-filter-buttons');
            const galleryItems = document.querySelectorAll('.gallery-item');
            const filterButtonsContainer = document.getElementById('filter-buttons');
            const bookViewHeader = document.getElementById('book-view-header');
            const backToBooksBtn = document.getElementById('back-to-books-btn');
            const bookViewTitle = document.getElementById('book-view-title');
            const backToYearSpan = document.getElementById('back-to-year');
            const backToTypeSpan = document.getElementById('back-to-type');
            
            let activeMainFilter = 'Printmaking';
            let activeSubFilter = '1970';
            let isBookViewActive = false;

            const subCategoriesMap = {
                'Printmaking': ['1970', '1980', '1990', '2000', '2010', '2020'],
                'painting': ['1970', '1980', '1990', '2000', '2010', '2020'],
                'on-paper': ['1970', '1980', '1990', '2000', '2010', '2020'],
                'art-book': ['2004', '2010', '2012', '2020'],
                'portfolio': ['1990', '2000', '2010', '2020']
            };

            function updateGallery() {
                const grid = document.getElementById('gallery-grid');
                grid.style.display = 'none';

                if (!isBookViewActive) {
                    if (activeMainFilter === 'art-book' || activeMainFilter === 'portfolio') {
                        grid.classList.remove('columns-1', 'sm:columns-2', 'lg:columns-3', 'xl:columns-4');
                        grid.classList.add('grid', 'grid-cols-1', 'sm:grid-cols-2', 'lg:grid-cols-3', 'xl:grid-cols-4', 'items-start');
                    } else {
                        grid.classList.remove('grid', 'grid-cols-1', 'sm:grid-cols-2', 'lg:grid-cols-3', 'xl:grid-cols-4', 'items-start');
                        grid.classList.add('columns-1', 'sm:columns-2', 'lg:columns-3', 'xl:columns-4');
                    }
                }

                galleryItems.forEach(item => {
                    const itemCategory = item.getAttribute('data-category');
                    const itemSubcat = item.getAttribute('data-subcat');
                    
                    const matchesMain = activeMainFilter === itemCategory;
                    
                    let matchesSub = true;
                    if (subCategoriesMap[activeMainFilter]) {
                        matchesSub = activeSubFilter === itemSubcat;
                    }

                    if (matchesMain && matchesSub) {
                        item.classList.remove('hidden-item');
                        item.classList.add('show-item');
                        item.style.display = ''; 
                    } else {
                        item.classList.remove('show-item');
                        item.classList.add('hidden-item');
                        item.style.display = 'none'; 
                    }
                });

                void grid.offsetHeight;
                grid.style.display = '';
            }

            function renderSubFilters(mainCategory) {
                subFilterContainer.innerHTML = '';
                
                if (subCategoriesMap[mainCategory]) {
                    subFilterContainer.classList.remove('hidden');
                    subFilterContainer.classList.add('flex');

                    subCategoriesMap[mainCategory].forEach(sub => {
                        const btn = document.createElement('button');
                        btn.setAttribute('data-subfilter', sub);
                        btn.textContent = sub;
                        if (activeSubFilter === sub) {
                            btn.className = 'sub-filter-btn px-4 py-1.5 rounded-full border border-dark bg-dark text-white text-sm font-medium transition-all';
                        } else {
                            btn.className = 'sub-filter-btn px-4 py-1.5 rounded-full border border-gray-300 bg-transparent text-gray-600 hover:border-dark hover:text-dark text-sm font-medium transition-all';
                        }
                        subFilterContainer.appendChild(btn);
                    });

                    const newSubBtns = subFilterContainer.querySelectorAll('.sub-filter-btn');
                    newSubBtns.forEach(button => {
                        button.addEventListener('click', () => {
                            newSubBtns.forEach(btn => {
                                btn.classList.remove('bg-dark', 'text-white', 'border-dark');
                                btn.classList.add('bg-transparent', 'text-gray-600', 'border-gray-300');
                            });
                            
                            button.classList.remove('bg-transparent', 'text-gray-600', 'border-gray-300');
                            button.classList.add('bg-dark', 'text-white', 'border-dark');

                            activeSubFilter = button.getAttribute('data-subfilter');
                            updateGallery();
                        });
                    });
                } else {
                    subFilterContainer.classList.add('hidden');
                    subFilterContainer.classList.remove('flex');
                }
            }

            filterButtons.forEach(button => {
                button.addEventListener('click', () => {
                    if (isBookViewActive) {
                        isBookViewActive = false;
                        document.querySelectorAll('.temp-book-page').forEach(el => el.remove());
                        document.getElementById('book-view-header').classList.remove('flex');
                        document.getElementById('book-view-header').classList.add('hidden');
                    }

                    filterButtons.forEach(btn => {
                        btn.classList.remove('bg-dark', 'text-white', 'border-dark');
                        btn.classList.add('bg-transparent', 'text-gray-600', 'border-gray-300');
                    });
                    
                    button.classList.remove('bg-transparent', 'text-gray-600', 'border-gray-300');
                    button.classList.add('bg-dark', 'text-white', 'border-dark');

                    activeMainFilter = button.getAttribute('data-filter');
                    
                    if (subCategoriesMap[activeMainFilter]) {
                        activeSubFilter = subCategoriesMap[activeMainFilter][0];
                    }
                    
                    renderSubFilters(activeMainFilter);
                    updateGallery();
                });
            });

            filterButtons.forEach(btn => {
                if (btn.getAttribute('data-filter') === activeMainFilter) {
                    btn.classList.remove('bg-transparent', 'text-gray-600', 'border-gray-300');
                    btn.classList.add('bg-dark', 'text-white', 'border-dark');
                } else {
                    btn.classList.remove('bg-dark', 'text-white', 'border-dark');
                    btn.classList.add('bg-transparent', 'text-gray-600', 'border-gray-300');
                }
            });
            renderSubFilters(activeMainFilter);
            updateGallery();

            const bookTriggers = document.querySelectorAll('.book-trigger');

            function openBookView(images, title, year, category) {
                isBookViewActive = true;
                
                filterButtonsContainer.classList.add('hidden');
                subFilterContainer.classList.remove('flex');
                subFilterContainer.classList.add('hidden');
                
                galleryItems.forEach(item => {
                    item.classList.remove('show-item');
                    item.classList.add('hidden-item');
                    item.style.display = 'none'; 
                });

                bookViewHeader.classList.remove('hidden');
                bookViewHeader.classList.add('flex');
                bookViewTitle.textContent = title;
                backToYearSpan.textContent = year;
                backToTypeSpan.textContent = category === 'portfolio' ? 'Portfolios' : 'Books';

                const grid = document.getElementById('gallery-grid');
                grid.classList.remove('columns-1', 'sm:columns-2', 'lg:columns-3', 'xl:columns-4');
                grid.classList.add('grid', 'grid-cols-1', 'sm:grid-cols-2', 'lg:grid-cols-3', 'xl:grid-cols-4', 'items-start');

                images.forEach((imgUrl, index) => {
                    const pageDiv = document.createElement('div');
                    pageDiv.className = 'temp-book-page lightbox-trigger cursor-pointer gallery-item group relative break-inside-avoid mb-4 md:mb-6 w-full overflow-hidden rounded-md bg-gray-200 show-item shadow-sm hover:shadow-xl';
                    
                    const labelText = index === 0 ? 'Cover' : 'Page ' + index;

                    pageDiv.setAttribute('data-title', title + ' - ' + labelText);
                    pageDiv.setAttribute('data-info', category === 'portfolio' ? 'Portfolio Page' : 'Art Book Page');

                    pageDiv.innerHTML = `
                        <img src="${imgUrl}" alt="${labelText}" class="w-full h-auto block transition-transform duration-700 group-hover:scale-105" loading="lazy">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-6">
                            <span class="text-white text-lg font-serif font-bold">${labelText}</span>
                        </div>
                    `;
                    grid.appendChild(pageDiv);
                });
                
                document.getElementById('portfolio').scrollIntoView({ behavior: 'smooth' });
            }

            backToBooksBtn.addEventListener('click', () => {
                isBookViewActive = false;
                
                document.querySelectorAll('.temp-book-page').forEach(el => el.remove());
                
                bookViewHeader.classList.remove('flex');
                bookViewHeader.classList.add('hidden');
                
                filterButtonsContainer.classList.remove('hidden');
                if (subCategoriesMap[activeMainFilter]) {
                    subFilterContainer.classList.remove('hidden');
                    subFilterContainer.classList.add('flex');
                }

                updateGallery();
            });

            bookTriggers.forEach(trigger => {
                trigger.addEventListener('click', (e) => {
                    if(e.target.closest('.filter-btn')) return;
                    
                    const imagesRaw = trigger.getAttribute('data-book-contents');
                    const title = trigger.getAttribute('data-book-title');
                    const year = trigger.getAttribute('data-subcat');
                    const category = trigger.getAttribute('data-category');
                    
                    if (imagesRaw) {
                        try {
                            const images = JSON.parse(imagesRaw);
                            openBookView(images, title, year, category);
                        } catch (err) {
                            console.error("Error parsing book images", err);
                        }
                    }
                });
            });

            // --- Elegant Lightbox & Navigation Logic ---
            const lightbox = document.getElementById('lightbox');
            const lightboxImg = document.getElementById('lightbox-img');
            const galleryGrid = document.getElementById('gallery-grid');
            
            let currentLightboxItems = [];
            let currentLightboxIndex = -1;

            function updateLightboxUI(index) {
                const item = currentLightboxItems[index];
                const img = item.querySelector('img');
                
                lightboxImg.src = img.src;
                
                const rawTitle = item.getAttribute('data-title') || 'Artwork';
                const titleParts = rawTitle.split(' - ');
                document.getElementById('lightbox-title').textContent = titleParts[0]; 
                
                document.getElementById('lightbox-info').textContent = item.getAttribute('data-info') || '';

                document.getElementById('lightbox-prev').disabled = (index === 0);
                document.getElementById('lightbox-next').disabled = (index === currentLightboxItems.length - 1);
            }

            galleryGrid.addEventListener('click', (e) => {
                const item = e.target.closest('.lightbox-trigger');
                if (!item) return;

                e.preventDefault(); 

                if (isBookViewActive) {
                    currentLightboxItems = Array.from(document.querySelectorAll('.temp-book-page.lightbox-trigger'));
                } else {
                    currentLightboxItems = Array.from(document.querySelectorAll('.gallery-item.show-item.lightbox-trigger'));
                }
                
                currentLightboxIndex = currentLightboxItems.indexOf(item);
                
                if (currentLightboxIndex > -1) {
                    updateLightboxUI(currentLightboxIndex);
                    
                    lightbox.classList.remove('hidden');
                    document.body.style.overflow = 'hidden'; 
                    
                    setTimeout(() => {
                        lightbox.classList.remove('opacity-0');
                    }, 20);
                }
            });

            document.getElementById('lightbox-prev').addEventListener('click', (e) => {
                e.stopPropagation();
                if (currentLightboxIndex > 0) {
                    currentLightboxIndex--;
                    updateLightboxUI(currentLightboxIndex);
                }
            });

            document.getElementById('lightbox-next').addEventListener('click', (e) => {
                e.stopPropagation();
                if (currentLightboxIndex < currentLightboxItems.length - 1) {
                    currentLightboxIndex++;
                    updateLightboxUI(currentLightboxIndex);
                }
            });

            function closeLightbox() {
                lightbox.classList.add('opacity-0');
                document.body.style.overflow = ''; 
                setTimeout(() => {
                    lightbox.classList.add('hidden');
                    lightboxImg.src = '';
                }, 300);
            }

            document.getElementById('lightbox-close').addEventListener('click', closeLightbox);
            
            lightbox.addEventListener('click', (e) => {
                if (e.target === lightbox || e.target.classList.contains('min-h-screen')) {
                    closeLightbox();
                }
            });
            
            document.addEventListener('keydown', (e) => {
                if (!lightbox.classList.contains('hidden')) {
                    if (e.key === 'Escape') closeLightbox();
                    if (e.key === 'ArrowLeft' && currentLightboxIndex > 0) {
                        currentLightboxIndex--;
                        updateLightboxUI(currentLightboxIndex);
                    }
                    if (e.key === 'ArrowRight' && currentLightboxIndex < currentLightboxItems.length - 1) {
                        currentLightboxIndex++;
                        updateLightboxUI(currentLightboxIndex);
                    }
                }
            });
        });
    </script>
</body>
</html>