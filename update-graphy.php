<?php
/**
 * Script to dynamically generate Graphy.md file based on current directory structure
 * 
 * Usage: php update-graphy.php
 */

class GraphyGenerator {
    private $basePath;
    private $outputFile = 'Graphy.md';
    private $excludedPatterns = [
        '/^\.git$/',
        '/^\.gitignore$/',
        '/^update-graphy\.php$/',
        '/^Update-Graphy\.ps1$/',
        '/^update-graphy\.bat$/',
        '/^Graphy\.md$/',
        '/\.tmp$/',
        '/\.log$/',
        '/__pycache__/'
    ];
    
    public function __construct($basePath) {
        $this->basePath = rtrim($basePath, '/\\');
    }
    
    public function generate() {
        $files = $this->scanDirectory($this->basePath);
        $content = $this->buildContent($files);
        file_put_contents($this->basePath . '/' . $this->outputFile, $content);
        echo "Graphy.md has been updated with current directory structure.\n";
    }
    
    private function scanDirectory($path, $relativePath = '') {
        $items = [];
        $dir = new DirectoryIterator($path);
        
        foreach ($dir as $item) {
            if ($item->isDot()) {
                continue;
            }
            
            $name = $item->getFilename();
            $fullPath = $item->getPathname();
            $relPath = $relativePath ? $relativePath . '/' . $name : $name;
            
            // Check if excluded
            $excluded = false;
            foreach ($this->excludedPatterns as $pattern) {
                if (preg_match($pattern, $name) || preg_match($pattern, $relPath)) {
                    $excluded = true;
                    break;
                }
            }
            
            if ($excluded) {
                continue;
            }
            
            if ($item->isDir()) {
                $items[$relPath] = [
                    'type' => 'dir',
                    'name' => $name,
                    'path' => $relPath,
                    'children' => $this->scanDirectory($fullPath, $relPath)
                ];
            } else {
                $items[$relPath] = [
                    'type' => 'file',
                    'name' => $name,
                    'path' => $relPath,
                    'extension' => pathinfo($name, PATHINFO_EXTENSION)
                ];
            }
        }
        
        // Sort: directories first, then files, alphabetically
        uksort($items, function($a, $b) use ($items) {
            $aIsDir = $items[$a]['type'] === 'dir';
            $bIsDir = $items[$b]['type'] === 'dir';
            
            if ($aIsDir && !$bIsDir) return -1;
            if (!$aIsDir && $bIsDir) return 1;
            
            return strcasecmp($a, $b);
        });
        
        return $items;
    }
    
    private function buildContent($files) {
        $fileStats = $this->countFileTypes($files);
        
        $content = "# Graphy Codebase Analysis\n\n";
        $content .= "## Project: " . basename($this->basePath) . "\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        // File Structure Summary
        $content .= "### File Structure Summary\n";
        $content .= "- Total Files: " . $fileStats['fileCount'] . "\n";
        $content .= "- Total Directories: " . $fileStats['dirCount'] . "\n";
        
        if (!empty($fileStats['extensions'])) {
            $content .= "- File Extensions: ";
            $extStrings = [];
            foreach ($fileStats['extensions'] as $ext => $count) {
                $extStrings[] = ".$ext: $count";
            }
            $content .= implode(', ', $extStrings) . "\n";
        }
        $content .= "\n";
        
        // Directory Tree
        $content .= "### Directory Tree\n";
        $content .= $this->buildTree($files, 0);
        $content .= "\n";
        
        // File List (first 50 files)
        $content .= "### File List (First 50 Files)\n";
        $fileList = $this->getFileList($files);
        $counter = 0;
        foreach ($fileList as $filePath) {
            if ($counter >= 50) {
                $content .= "\n... and " . (count($fileList) - 50) . " more files\n";
                break;
            }
            $content .= "- " . $this->basePath . '/' . $filePath . "\n";
            $counter++;
        }
        
        // Maintenance Instructions
        $content .= "\n### Maintenance\n";
        $content .= "This file is auto-generated. To update it:\n";
        $content .= "1. Run `php update-graphy.php`\n";
        $content .= "2. Or run `./Update-Graphy.ps1` (PowerShell)\n";
        $content .= "3. Or run `update-graphy.bat` (Windows batch)\n";
        $content .= "\nThe file will be regenerated with current directory structure.\n";
        
        return $content;
    }
    
    private function countFileTypes($items) {
        $stats = [
            'fileCount' => 0,
            'dirCount' => 0,
            'extensions' => []
        ];
        
        foreach ($items as $item) {
            if ($item['type'] === 'dir') {
                $stats['dirCount']++;
                $childStats = $this->countFileTypes($item['children']);
                $stats['fileCount'] += $childStats['fileCount'];
                $stats['dirCount'] += $childStats['dirCount'];
                foreach ($childStats['extensions'] as $ext => $count) {
                    $stats['extensions'][$ext] = ($stats['extensions'][$ext] ?? 0) + $count;
                }
            } else {
                $stats['fileCount']++;
                $ext = strtolower($item['extension']);
                if ($ext) {
                    $stats['extensions'][$ext] = ($stats['extensions'][$ext] ?? 0) + 1;
                }
            }
        }
        
        return $stats;
    }
    
    private function buildTree($items, $depth) {
        $output = '';
        $prefix = str_repeat('   ', $depth);
        
        foreach ($items as $item) {
            if ($item['type'] === 'dir') {
                $output .= $prefix . "├── " . $item['name'] . "\n";
                $output .= $this->buildTree($item['children'], $depth + 1);
            } else {
                $output .= $prefix . "├── " . $item['name'] . "\n";
            }
        }
        
        return $output;
    }
    
    private function getFileList($items) {
        $files = [];
        
        foreach ($items as $item) {
            if ($item['type'] === 'dir') {
                $files = array_merge($files, $this->getFileList($item['children']));
            } else {
                $files[] = $item['path'];
            }
        }
        
        sort($files);
        return $files;
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    $basePath = __DIR__;
    $generator = new GraphyGenerator($basePath);
    $generator->generate();
} else {
    echo "This script should be run from command line.\n";
}
?>