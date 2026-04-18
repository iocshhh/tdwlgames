<?php

class Player {
    private $rank;
    private $title;
    private $points;
    private $badge;
    private $codeName;
    private $photo;

    public function __construct($rank, $title, $points, $badge, $codeName, $photo = null) {
        $this->rank = $rank;
        $this->title = $title;
        $this->points = $points;
        $this->badge = $badge;
        $this->codeName = $codeName;
        $this->photo = $photo;
    }

    public function getRank() { return $this->rank; }
    public function getTitle() { return $this->title; }
    public function getPoints() { return $this->points; }
    public function getBadge() { return $this->badge; }
    public function getCodeName() { return $this->codeName; }

    public function getRankLabel() {
        $labels = [1 => "Top 1", 2 => "Top 2", 3 => "Top 3"];
        return $labels[$this->rank] ?? "Top " . $this->rank;
    }

    public function renderRankCircle() {
        $photoHtml = '';
        if ($this->photo) {
            $photoHtml = '<img src="' . htmlspecialchars($this->photo) . '" class="rank-photo" alt="' . htmlspecialchars($this->title) . '">';
        }
        $rankBadge = '<img src="img/rank-' . htmlspecialchars($this->rank) . '.png" class="rank-badge" alt="Rank ' . htmlspecialchars($this->rank) . '">';
        return sprintf(
            '<div class="rank-circle" data-rank="%s">%s%s</div>',
            htmlspecialchars($this->getRankLabel()),
            $photoHtml,
            $rankBadge
        );
    }

    public function renderRankMeta() {
        $mvpCount = (int)$this->badge;
        
        return sprintf(
            '<div class="rank-meta">
                <div class="codename-badge">%s</div>
                <div class="stats-row">
                    <div class="stat-column">
                        <div class="stat-number">%d</div>
                        <div class="stat-underline"></div>
                        <div class="stat-label">points</div>
                    </div>
                    <div class="stat-divider">|</div>
                    <div class="stat-column">
                        <div class="stat-number">%d</div>
                        <div class="stat-underline"></div>
                        <div class="stat-label">mvp</div>
                    </div>
                </div>
                <div class="player-name">%s</div>
            </div>',
            htmlspecialchars($this->codeName),
            $this->points,
            $mvpCount,
            htmlspecialchars($this->title)
        );
    }
}

class LeaderboardEntry {
    private $position;
    private $player1;
    private $player2;
    private $score;

    public function __construct($position, $player1, $player2, $score) {
        $this->position = $position;
        $this->player1 = $player1;
        $this->player2 = $player2;
        $this->score = $score;
    }

    public function render() {
        return '<tr><td class="position">' . (int)$this->position . '</td><td class="player-name">' . htmlspecialchars($this->player1) . '</td><td class="player-codename clickable-codename" onclick="openPlayerModal(\'' . htmlspecialchars($this->player2) . '\')">' . htmlspecialchars($this->player2) . '</td><td class="score">' . htmlspecialchars($this->score) . '</td></tr>';
    }
}

class Leaderboard {
    private $title;
    private $entries;

    public function __construct($title) {
        $this->title = $title;
        $this->entries = [];
    }

    public function addEntry(LeaderboardEntry $entry) {
        $this->entries[] = $entry;
    }

    public function render() {
        $html = '<div class="leaderboard card"><div class="leaderboard-title">' . htmlspecialchars($this->title) . '</div><table class="leaderboard-table"><thead><tr><th>No.</th><th>Name</th><th>Codename</th><th>' . ($this->title === 'MVP Leaderboard' ? 'MVP' : 'Points') . '</th></tr></thead><tbody>';
        
        foreach ($this->entries as $entry) {
            $html .= $entry->render();
        }

        $html .= '</tbody></table></div>';
        return $html;
    }
}

class GamePage {
    private $topPlayers;
    private $pointsLeaderboard;
    private $mvpLeaderboard;
    private $debugInfo = [];
    private $allPlayers = [];
    private $topPlayerRanks = [];

    public function __construct() {
        $this->topPlayers = [];
        $this->pointsLeaderboard = new Leaderboard("Points Leaderboard");
        $this->mvpLeaderboard = new Leaderboard("MVP Leaderboard");
        $this->initializeData();
    }

    public function getDebugInfo() {
        return $this->debugInfo;
    }

    private function initializeData() {
        $apiUrl = "https://script.google.com/macros/s/AKfycbyUxiVxRcHWcVhkGWuxKZVBwHfzIkgH5r2itvqh-06vQYf9p07FlWfMkkPWneJ9oJJgQQ/exec";
        
        $players = $this->fetchPlayersFromAPI($apiUrl);
        
        if (empty($players)) {
            // Fallback to default data if API fails
            $this->loadDefaultData();
            return;
        }

        // Store all players for modal access
        $this->allPlayers = $players;

        // Sort by points (highest first)
        usort($players, function($a, $b) {
            return ($b['points'] ?? 0) - ($a['points'] ?? 0);
        });

        // Build top 3 players
        for ($i = 0; $i < min(3, count($players)); $i++) {
            $player = $players[$i];
            $mvpCount = (int)($player['mvp'] ?? 0);
            $badge = $mvpCount > 0 ? $mvpCount : '';
            $codename = $player['codename'] ?? 'Unknown';
            $rank = $i + 1;
            
            // Track rank for this player
            $this->topPlayerRanks[$codename] = $rank;
            
            $this->topPlayers[] = new Player(
                $rank,
                $player['name'] ?? 'Player',
                $player['points'] ?? 0,
                $badge,
                $codename,
                $player['photo'] ?? null
            );
        }

        // Points Leaderboard - Top 10
        for ($i = 0; $i < min(10, count($players)); $i++) {
            $this->pointsLeaderboard->addEntry(new LeaderboardEntry(
                $i + 1,
                $players[$i]['name'] ?? 'Unknown',
                $players[$i]['codename'] ?? 'N/A',
                (string)($players[$i]['points'] ?? 0)
            ));
        }

        // Sort by MVP count (highest first) for MVP leaderboard
        $mvpSortedPlayers = $players;
        usort($mvpSortedPlayers, function($a, $b) {
            return ($b['mvp'] ?? 0) - ($a['mvp'] ?? 0);
        });

        // MVP Leaderboard - Top 10
        for ($i = 0; $i < min(10, count($mvpSortedPlayers)); $i++) {
            $mvpCount = (int)($mvpSortedPlayers[$i]['mvp'] ?? 0);
            $this->mvpLeaderboard->addEntry(new LeaderboardEntry(
                $i + 1,
                $mvpSortedPlayers[$i]['name'] ?? 'Unknown',
                $mvpSortedPlayers[$i]['codename'] ?? 'N/A',
                (string)$mvpCount
            ));
        }
    }

    private function fetchPlayersFromAPI($url) {
        $this->debugInfo[] = "Attempting to fetch from: $url";

        // Try cURL first (more reliable)
        if (function_exists('curl_init')) {
            $this->debugInfo[] = "Using cURL method";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                $this->debugInfo[] = "cURL Error: $curlError";
            } else {
                $this->debugInfo[] = "cURL HTTP Code: $httpCode";
            }
        } else {
            $this->debugInfo[] = "cURL not available, attempting file_get_contents";
            
            // Fallback to file_get_contents
            if (!ini_get('allow_url_fopen')) {
                $this->debugInfo[] = "ERROR: allow_url_fopen is disabled";
                return [];
            }

            $options = [
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,
                    'header' => "Accept: application/json\r\nUser-Agent: PHP\r\n",
                    'ignore_errors' => true,
                ]
            ];
            
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                $this->debugInfo[] = "ERROR: file_get_contents failed";
                return [];
            }
        }

        if (empty($response)) {
            $this->debugInfo[] = "ERROR: Empty response from API";
            return [];
        }

        $this->debugInfo[] = "Response received, length: " . strlen($response) . " bytes";
        $this->debugInfo[] = "Response (first 300 chars): " . substr($response, 0, 300);

        // Try to decode the JSON response
        $data = @json_decode($response, true);
        
        if ($data === null) {
            $this->debugInfo[] = "ERROR: Invalid JSON - " . json_last_error_msg();
            return [];
        }

        if (!is_array($data)) {
            $this->debugInfo[] = "ERROR: Response is not an array, got " . gettype($data);
            return [];
        }

        $this->debugInfo[] = "API returned " . count($data) . " records";

        // If response is wrapped in a 'data' key, unwrap it
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
            $this->debugInfo[] = "Unwrapped data key, now have " . count($data) . " records";
        }

        // Convert array keys to lowercase for consistency
        $normalizedData = array_map(function($item) {
            if (!is_array($item)) {
                return $item;
            }
            $normalized = [];
            foreach ($item as $key => $value) {
                $normalized[strtolower($key)] = $value;
            }
            return $normalized;
        }, $data);

        $this->debugInfo[] = "Normalized data keys";
        if (!empty($normalizedData)) {
            $this->debugInfo[] = "First record keys: " . implode(", ", array_keys($normalizedData[0]));
            $this->debugInfo[] = "First record: " . json_encode($normalizedData[0]);
        }

        return $normalizedData;
    }

    private function loadDefaultData() {
        // Sample data for testing when API is unavailable
        $this->allPlayers = [
            [
                'name' => 'Alice Johnson',
                'codename' => 'AJ',
                'points' => 200,
                'mvp' => 8,
                'photo' => null
            ],
            [
                'name' => 'Bob Smith',
                'codename' => 'BS',
                'points' => 180,
                'mvp' => 6,
                'photo' => null
            ],
            [
                'name' => 'Charlie Brown',
                'codename' => 'CB',
                'points' => 160,
                'mvp' => 4,
                'photo' => null
            ],
            [
                'name' => 'Diana Prince',
                'codename' => 'DP',
                'points' => 140,
                'mvp' => 3,
                'photo' => null
            ],
            [
                'name' => 'Eve Wilson',
                'codename' => 'EW',
                'points' => 120,
                'mvp' => 2,
                'photo' => null
            ]
        ];
    }

    public function renderRankingPanel() {
        $html = '<div class="ranking-panel">';
        foreach ($this->topPlayers as $player) {
            $html .= sprintf(
                '<article class="rank-item">%s%s</article>',
                $player->renderRankCircle(),
                $player->renderRankMeta()
            );
        }
        $html .= '</div>';
        return $html;
    }

    public function renderLeaderboards() {
        return $this->pointsLeaderboard->render() . $this->mvpLeaderboard->render();
    }

    public function getPlayerDataJson() {
        $playerData = [];
        foreach ($this->allPlayers as $player) {
            $codename = $player['codename'] ?? 'N/A';
            $rank = isset($this->topPlayerRanks[$codename]) ? $this->topPlayerRanks[$codename] : null;
            
            $playerData[] = [
                'name' => $player['name'] ?? 'Unknown',
                'codename' => $codename,
                'points' => $player['points'] ?? 0,
                'mvp' => $player['mvp'] ?? 0,
                'photo' => $player['photo'] ?? null,
                'rank' => $rank
            ];
        }
        return htmlspecialchars(json_encode($playerData), ENT_QUOTES, 'UTF-8');
    }

    public function render() {
        return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TDWL Games</title>
  <style>
    @font-face {
      font-family: Alagard;
      src: url(font/alagard.ttf) format(truetype);
    }

    :root {
      color-scheme: dark;
      --bg: #5a00ff;
      --surface: rgba(255,255,255,0.06);
      --border: rgba(255,255,255,0.9);
      --text: #ffffff;
      --muted: rgba(255,255,255,0.75);
      --shadow: 0 20px 80px rgba(0,0,0,0.18);
      --radius: 24px;
      font-family: "Inter", "Segoe UI", system-ui, sans-serif;
    }

    @keyframes gradientRotate {
      0% {
        background: linear-gradient(0deg, #ff69b4, #ffffff, #9370db, #ff69b4);
        box-shadow: 0 0 20px rgba(255, 105, 180, 0.6), 0 0 30px rgba(255, 255, 255, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      6.25% {
        background: linear-gradient(22deg, #ff1493, #ffb6c1, #9370db, #ff1493);
        box-shadow: 0 0 21px rgba(255, 20, 147, 0.6), 0 0 31px rgba(255, 182, 193, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      12.5% {
        background: linear-gradient(45deg, #ffffff, #9370db, #ff69b4, #ffffff);
        box-shadow: 0 0 22px rgba(255, 160, 180, 0.6), 0 0 32px rgba(200, 112, 219, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      18.75% {
        background: linear-gradient(67deg, #ffc0cb, #ffffff, #ff1493, #ffc0cb);
        box-shadow: 0 0 23px rgba(255, 192, 203, 0.6), 0 0 33px rgba(255, 255, 255, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      25% {
        background: linear-gradient(90deg, #ffffff, #9370db, #ff69b4, #ffffff);
        box-shadow: 0 0 25px rgba(255, 255, 255, 0.6), 0 0 35px rgba(147, 112, 219, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      31.25% {
        background: linear-gradient(112deg, #ffb6c1, #ff69b4, #9370db, #ffb6c1);
        box-shadow: 0 0 24px rgba(255, 182, 193, 0.6), 0 0 34px rgba(255, 105, 180, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      37.5% {
        background: linear-gradient(135deg, #9370db, #ff69b4, #ffffff, #9370db);
        box-shadow: 0 0 23px rgba(200, 112, 219, 0.6), 0 0 33px rgba(255, 160, 180, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      43.75% {
        background: linear-gradient(157deg, #ff1493, #ffffff, #ffb6c1, #ff1493);
        box-shadow: 0 0 22px rgba(255, 20, 147, 0.6), 0 0 32px rgba(255, 192, 203, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      50% {
        background: linear-gradient(180deg, #9370db, #ff69b4, #ffffff, #9370db);
        box-shadow: 0 0 20px rgba(147, 112, 219, 0.6), 0 0 30px rgba(255, 105, 180, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      56.25% {
        background: linear-gradient(202deg, #ffc0cb, #ff69b4, #ffffff, #ffc0cb);
        box-shadow: 0 0 21px rgba(255, 192, 203, 0.6), 0 0 31px rgba(255, 105, 180, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      62.5% {
        background: linear-gradient(225deg, #ff69b4, #ffffff, #9370db, #ff69b4);
        box-shadow: 0 0 22px rgba(255, 160, 180, 0.6), 0 0 32px rgba(255, 255, 255, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      68.75% {
        background: linear-gradient(247deg, #ffffff, #ffb6c1, #ff1493, #ffffff);
        box-shadow: 0 0 23px rgba(255, 255, 255, 0.6), 0 0 33px rgba(255, 182, 193, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      75% {
        background: linear-gradient(270deg, #ff69b4, #ffffff, #9370db, #ff69b4);
        box-shadow: 0 0 25px rgba(255, 105, 180, 0.6), 0 0 35px rgba(255, 255, 255, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      81.25% {
        background: linear-gradient(292deg, #ffb6c1, #ff69b4, #ffffff, #ffb6c1);
        box-shadow: 0 0 24px rgba(255, 182, 193, 0.6), 0 0 34px rgba(255, 105, 180, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      87.5% {
        background: linear-gradient(315deg, #ffffff, #9370db, #ff69b4, #ffffff);
        box-shadow: 0 0 23px rgba(255, 255, 255, 0.6), 0 0 33px rgba(180, 112, 219, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      93.75% {
        background: linear-gradient(337deg, #ff1493, #ffc0cb, #9370db, #ff1493);
        box-shadow: 0 0 22px rgba(255, 20, 147, 0.6), 0 0 32px rgba(255, 192, 203, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
      100% {
        background: linear-gradient(360deg, #ff69b4, #ffffff, #9370db, #ff69b4);
        box-shadow: 0 0 20px rgba(255, 105, 180, 0.6), 0 0 30px rgba(255, 255, 255, 0.4), inset 0 0 20px rgba(0,0,0,0.3);
      }
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      background: url(\'img/body-bg.png\') center/cover fixed no-repeat;
      color: var(--text);
      display: flex;
      justify-content: center;
      padding: 32px 16px;
    }

    .page {
      width: min(1200px, 100%);
      display: grid;
      gap: 32px;
    }

    .card {
      border: 2px solid var(--border);
      border-radius: var(--radius);
      padding: 28px;
      box-shadow: var(--shadow);
      background: rgba(0,0,0,0.08);
      backdrop-filter: blur(12px);
    }

    .ranking-panel {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 32px 20px;
      align-items: start;
      text-align: center;
    }

    .rank-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 16px;
      background: url(\'img/ranking-card-bg.png\') center/cover no-repeat;
      margin-top: 12px;
      padding: -28px 20px;
      border-radius: 12px;
      border: 1px solid rgba(255,255,255,0.1);
      box-shadow: 0 8px 32px rgba(0,0,0,0.3);
      min-height: 420px;
      justify-content: flex-start;
    }

    .rank-item:nth-child(1) {
      border-top: 1px solid white;
    }

    .rank-item:nth-child(2) {
      border-top: 1px solid white;
    }

    .rank-item:nth-child(3) {
      border-top: 1px solid white;
    }

    .rank-circle {
      width: clamp(160px, 22vw, 280px);
      height: clamp(160px, 22vw, 280px);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 12px;
      position: relative;
      margin: 10px auto -60px auto;
      background: linear-gradient(135deg, rgba(255,255,255,0.05), rgba(255,255,255,0.01));
      backdrop-filter: blur(10px);
      border: 3px solid transparent;
      background-clip: padding-box;
      animation: gradientRotate 6s linear infinite;
      transition: all 0.3s ease;
    }

    .rank-item:nth-child(1) .rank-circle {
      animation: gradientRotate 6s linear infinite;
    }

    .rank-item:nth-child(1) .rank-circle:hover {
      animation-duration: 3s;
    }

    .rank-item:nth-child(2) .rank-circle {
      animation: gradientRotate 6s linear infinite;
      animation-delay: -2s;
    }

    .rank-item:nth-child(2) .rank-circle:hover {
      animation-duration: 3s;
    }

    .rank-item:nth-child(3) .rank-circle {
      animation: gradientRotate 6s linear infinite;
      animation-delay: -4s;
    }

    .rank-item:nth-child(3) .rank-circle:hover {
      animation-duration: 3s;
    }

    .rank-circle::before {
      content: attr(data-rank);
      position: absolute;
      top: -18px;
      left: 50%;
      transform: translateX(-50%);
      font-size: 0.8rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: #ffffff;
      background: rgba(0,0,0,0.4);
      padding: 3px 10px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.3);
      font-weight: 700;
      backdrop-filter: blur(10px);
      white-space: nowrap;
      display: none;
    }

    .rank-badge {
      position: absolute;
      width: 180px;
      height: 180px;
      object-fit: contain;
      z-index: 2;
      top: -95px;
      left: 50%;
      transform: translateX(-50%);
    }

    .rank-circle span {
      display: block;
      font-size: clamp(2.2rem, 5vw, 3.2rem);
      font-weight: 900;
      color: #ffffff;
      letter-spacing: -0.02em;
      position: relative;
      z-index: 1;
    }

    .rank-photo {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
      display: block;
      position: absolute;
      top: 0;
      left: 0;
    }

    .rank-meta {
      display: flex;
      flex-direction: column;
      gap: 12px;
      align-items: center;
      text-align: center;
      background: url(\'img/rank-item-bg.png\') center/100% auto no-repeat;
      padding: 20px 16px 150px 16px;
      border-radius: 12px;
      width: 100%;
    }

    .codename-badge {
      font-family: Alagard, Arial;
      font-size: 1.6rem;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: #9370db;
      font-weight: 900;
      padding: 8px 18px;
      margin-top: 15px;
      text-shadow: 3px 3px 0px #4a2a6f, 6px 6px 12px rgba(74, 42, 111, 0.8);
      -webkit-text-stroke: 2px #ffffff;
    }

    .stats-row {
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      align-items: center;
      justify-items: center;
      gap: 8px;
      width: 100%;
      margin: 10px 0;
    }

    .stat-column {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
    }

    .stat-number {
      font-size: 2.2rem;
      font-weight: 900;
      color: #ffffff;
      line-height: 1;
    }

    .stat-divider {
      font-size: 1.2rem;
      color: rgba(255,255,255,0.5);
      margin: 0 5px;
    }

    .stat-underline {
      width: 70%;
      height: 2px;
      background: linear-gradient(90deg, rgba(255,255,255,0.3), rgba(255,255,255,0.8), rgba(255,255,255,0.3));
      margin: 4px 0;
    }

    .stat-label {
      font-size: 0.85rem;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.8);
      font-weight: 700;
    }

    .points-value {
      font-size: 2.4rem;
      font-weight: 900;
      color: #ffd700;
      line-height: 1;
      letter-spacing: -0.02em;
    }

    .points-label {
      display: inline-block;
      font-size: 0.95rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.9);
      font-weight: 700;
    }

    .mvp-badge {
      display: inline-block;
      font-size: 0.95rem;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: #1a1a1a;
      font-weight: 800;
      padding: 6px 12px;
      border-radius: 4px;
      background: #ffd700;
      border: 1px solid #fff8dc;
      box-shadow: 0 3px 10px rgba(255,215,0,0.5);
    }

    .player-name {
      margin: 0;
      font-size: 1.25rem;
      font-weight: 700;
      color: #ffffff;
      letter-spacing: 0.04em;
      text-shadow: 0 2px 8px rgba(0,0,0,0.4);
    }

    .leaderboards {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 22px;
    }

    .leaderboard {
      display: grid;
      gap: 16px;
      min-height: 250px;
    }

    .leaderboard-header {
      border-bottom: 2px solid var(--border);
      padding-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 0.2em;
      font-size: 0.95rem;
      color: #fff;
    }

    .leaderboard-title {
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 0.2em;
      font-size: 0.95rem;
      font-weight: 700;
      color: #fff;
      margin: 0 0 4px 0;
      padding-bottom: 4px;
      border-bottom: 2px solid var(--border);
    }

    .leaderboard-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
      color: #fff;
      margin-top: 0;
    }

    .leaderboard-table thead {
      background: rgba(255,255,255,0.08);
      border-bottom: 2px solid rgba(255,255,255,0.3);
    }

    .leaderboard-table th {
      padding: 8px 12px;
      text-align: left;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.15em;
      color: #ffffff;
      font-size: 0.8rem;
    }

    .leaderboard-table th:last-child {
      text-align: right;
    }

    .leaderboard-table tbody tr {
      border-bottom: 1px solid rgba(255,255,255,0.1);
      transition: all 0.25s ease;
    }

    .leaderboard-table tbody tr:nth-child(even) {
      background-color: rgba(255,255,255,0.03);
    }

    .leaderboard-table tbody tr:hover {
      background-color: rgba(255,255,255,0.08);
      transform: translateX(4px);
      border-left: 3px solid rgba(255,255,255,0.5);
      padding-left: 9px;
    }

    .leaderboard-table td {
      padding: 8px 12px;
      color: #fff;
      font-size: 0.9rem;
    }

    .leaderboard-table td.position {
      font-weight: 800;
      width: 35px;
      text-align: center;
      font-size: 1rem;
      color: rgba(255,255,255,0.9);
    }

    .leaderboard-table td.player-name {
      font-weight: 700;
      word-break: break-word;
      color: #ffffff;
    }

    .leaderboard-table td.player-codename {
      color: rgba(255,255,255,0.7);
      font-style: italic;
      font-weight: 500;
    }

    .leaderboard-table td.score {
      color: rgba(255,255,255,0.9);
      font-weight: 800;
      text-align: right;
      font-size: 0.95rem;
    }

    .footer-bar {
      border: 2px solid var(--border);
      border-radius: calc(var(--radius) / 1.3);
      padding: 18px 24px;
      text-align: center;
      font-size: 0.95rem;
      letter-spacing: 0.2em;
      color: #ffffff;
      text-transform: uppercase;
      background: rgba(0,0,0,0.08);
    }

    .footer-button {
      display: inline-block;
      color: #ffffff;
      text-decoration: none;
      transition: all 0.3s ease;
    }

    .footer-button:hover {
      color: #9370db;
      transform: scale(1.05);
    }

    .sticky-logo-header {
      position: sticky;
      top: 0;
      z-index: 999;
      display: flex;
      justify-content: center;
      align-items: center;
      width: 100%;
      height: 100px;
      background: linear-gradient(180deg, rgba(90, 0, 255, 0.9), rgba(90, 0, 255, 0.5) 80%, transparent);
      backdrop-filter: blur(10px);
      padding: 10px 0;
      box-shadow: 0 4px 20px rgba(147, 112, 219, 0.3);
    }

    .sticky-logo-header img {
      max-width: 280px;
      max-height: 80px;
      object-fit: contain;
    }

    .highlight {
      color: #ffdd55;
    }

    @media (max-width: 980px) {
      .ranking-panel {
        grid-template-columns: 1fr;
      }

      .leaderboards {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 640px) {
      body {
        padding: 18px 12px;
      }

      .card {
        padding: 22px;
      }

      .rank-item {
        min-height: 450px;
      }

      .rank-meta {
        padding: 20px 16px 75px 16px;
        gap: 14px;
      }

      .codename-badge {
        font-size: 1.2rem;
        padding: 7px 16px;
        margin-top: 25px;
      }

      .stat-column {
        gap: 5px;
      }

      .stat-number {
        font-size: 1.9rem;
      }

      .stat-label {
        font-size: 0.8rem;
      }

      .stat-underline {
        width: 65%;
        height: 1.5px;
      }

      .stat-divider {
        font-size: 1rem;
      }

      .player-name {
        font-size: 1.15rem;
      }

      .rank-meta strong,
      .rank-meta p,
      .leaderboard-title,
      .leaderboard-table th,
      .leaderboard-table td,
      .footer-bar {
        font-size: 0.85rem;
      }

      .leaderboard-table th,
      .leaderboard-table td {
        padding: 8px 6px;
      }
    }

    .clickable-codename {
      cursor: pointer;
      transition: color 0.3s ease;
    }

    .clickable-codename:hover {
      color: #9370db;
    }

    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }

    .modal.active {
      display: flex;
    }

    .modal-content {
      background: var(--surface);
      border: 2px solid var(--border);
      border-radius: var(--radius);
      padding: 30px;
      max-width: 500px;
      width: 90%;
      max-height: 80vh;
      overflow: visible;
      position: relative;
      box-shadow: var(--shadow);
    }

    .modal-content > *:not(.modal-rank-badge) {
      overflow-y: auto;
    }

    .modal-close {
      position: absolute;
      top: 15px;
      right: 20px;
      background: none;
      border: none;
      color: #ffffff;
      font-size: 1.8rem;
      cursor: pointer;
      transition: color 0.3s ease;
    }

    .modal-close:hover {
      color: #9370db;
    }

    .modal-player-header {
      text-align: center;
      margin-bottom: 25px;
      padding-top: 25px;
    }

    .modal-photo {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      margin: 0 auto 15px;
      border: 3px solid #9370db;
      object-fit: cover;
      display: block;
    }

    .modal-rank-badge {
      width: 200px;
      height: 200px;
      position: absolute;
      bottom: -83px;
      left: 50%;
      transform: translateX(-50%);
      object-fit: contain;
      pointer-events: none;
      z-index: 1050;
    }

    .modal-player-name {
      font-size: 1.4rem;
      font-weight: 800;
      color: #ffffff;
      margin-bottom: 5px;
    }

    .modal-player-codename {
      font-family: Alagard, Arial;
      font-size: 1.3rem;
      color: #9370db;
      font-weight: 700;
    }

    .modal-stats {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-top: 25px;
    }

    .modal-stat {
      background: rgba(147, 112, 219, 0.1);
      border: 2px solid #9370db;
      border-radius: 12px;
      padding: 15px;
      text-align: center;
    }

    .modal-stat-value {
      font-size: 2rem;
      font-weight: 900;
      color: #ffffff;
      margin-bottom: 8px;
    }

    .modal-stat-label {
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: rgba(255, 255, 255, 0.8);
    }
  </style>
</head>
<body>
  <header class="sticky-logo-header">
    <img src="img/tdwl-games-logo.PNG" alt="TDWL Games">
  </header>

  <main class="page">
    <section class="card">
      ' . $this->renderRankingPanel() . '
    </section>

    <section class="leaderboards">
      ' . $this->renderLeaderboards() . '
    </section>

    <section class="footer-bar">
      <a href="https://facebook.com/groups/3595874437295750/permalink/4511474632402388" class="footer-button" target="_blank">&lt;A&gt; <span class="highlight">TDWL-GAMES</span> &lt;/A&gt;</a>
    </section>
  </main>

  <div id="playerModal" class="modal" data-players=\'' . $this->getPlayerDataJson() . '\'>
    <div class="modal-content">
      <button class="modal-close" onclick="closePlayerModal()">&times;</button>
      <div class="modal-player-header">
        <div style="position: relative; display: inline-block; margin-bottom: 15px;">
          <img id="modalPhoto" class="modal-photo" src="" alt="Player Photo" style="display: none;">
          <img id="modalRankBadge" class="modal-rank-badge" src="" alt="Rank Badge" style="display: none;">
        </div>
        <div class="modal-player-name" id="modalPlayerName"></div>
        <div class="modal-player-codename" id="modalPlayerCodename"></div>
      </div>
      <div class="modal-stats">
        <div class="modal-stat">
          <div class="modal-stat-value" id="modalPoints">0</div>
          <div class="modal-stat-label">Points</div>
        </div>
        <div class="modal-stat">
          <div class="modal-stat-value" id="modalMvp">0</div>
          <div class="modal-stat-label">MVP</div>
        </div>
      </div>
    </div>
  </div>

  <script>
    var players = JSON.parse(document.getElementById("playerModal").getAttribute("data-players"));

    function openPlayerModal(codename) {
      var player = players.find(p => p.codename === codename);
      if (!player) return;

      document.getElementById("modalPlayerName").textContent = player.name;
      document.getElementById("modalPlayerCodename").textContent = player.codename;
      document.getElementById("modalPoints").textContent = player.points;
      document.getElementById("modalMvp").textContent = player.mvp;

      var photoImg = document.getElementById("modalPhoto");
      if (player.photo) {
        photoImg.src = player.photo;
        photoImg.style.display = "block";
      } else {
        photoImg.style.display = "none";
      }

      var rankBadgeImg = document.getElementById("modalRankBadge");
      if (player.rank && player.rank >= 1 && player.rank <= 3) {
        rankBadgeImg.src = "img/rank-" + player.rank + ".png";
        rankBadgeImg.style.display = "block";
      } else {
        rankBadgeImg.style.display = "none";
      }

      document.getElementById("playerModal").classList.add("active");
    }

    function closePlayerModal() {
      document.getElementById("playerModal").classList.remove("active");
    }

    document.getElementById("playerModal").addEventListener("click", function(e) {
      if (e.target === this) {
        closePlayerModal();
      }
    });
  </script>

</body>
</html>';
    }
}

// Initialize and render the page
$game = new GamePage();
echo $game->render();
?>
