<?php
declare(strict_types=1);

class worktimes {
    public bool $ignore = false;
    public array $data = [];

    public function start(string|int $id, ?string $title = null, bool $forceIgnore = false): void {
        if (!$this->ignore || $forceIgnore) {
            $this->data[$id]['title'] = $title ?? (string)$id;
            $this->data[$id]['start'] = microtime(true);
        }
    }

    public function stop(string|int|null $id = null): void {
        if ($id === null) {
            foreach (array_keys($this->data) as $key) {
                if (!isset($this->data[$key]['stop'])) {
                    $this->stop($key);
                }
            }
            return;
        }

        if (isset($this->data[$id])) {
            $this->data[$id]['stop'] = microtime(true);
            $this->data[$id]['result'] = $this->data[$id]['stop'] - $this->data[$id]['start'];
        }
    }

    public function getstrtime(float|int $tt): string {
        $tv = (float)$tt;
        $um = 'ms';
        $dd = 3;
        
        if ($tv < 1) {
            $dd = 6;
        }
        
        if ($tv > 1000) {
            $um = 'sec';
            $tv /= 1000;
            if ($tv > 60) {
                $um = 'min';
                $tv /= 60;
            }
        }
        
        return round($tv, $dd) . ' ' . $um;
    }

    public function get_item(string|int $key): string {
        $result = '';
        if (!empty($this->data) && isset($this->data[$key])) {
            if (!isset($this->data[$key]['result'])) {
                $this->stop($key);
            }
            $item = $this->data[$key];
            $result = $item['title'] . ": " . $this->getstrtime($item['result'] * 1000);
        }
        return $result;
    }

    public function get_list(): array {
        $result = [];
        if (!empty($this->data)) {
            $data = $this->data;
            
            uasort($data, static function(array $a, array $b): int {
                $resA = $a['result'] ?? 0.0;
                $resB = $b['result'] ?? 0.0;
                return $resB <=> $resA;
            });
            
            foreach (array_keys($data) as $key) {
                $result[] = $this->get_item($key);
            }
        }
        return $result;
    }
}