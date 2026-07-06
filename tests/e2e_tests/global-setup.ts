import { execSync } from 'node:child_process';
import path from 'node:path';

/**
 * E2E 実行前にフロントエンド資産（Vite ビルド成果物）を必ず最新化する。
 *
 * resources/js・resources/css を変更してもビルドし忘れると、ブラウザは
 * 古い public/build/assets を読み込み続けるため、実装済みの UI 変更が
 * 反映されないまま E2E が失敗する（原因調査に気付きにくい）。
 * これを防ぐため、テスト実行のたびに自動でビルドし直す。
 */
export default function globalSetup(): void {
  const repoRoot = path.resolve(__dirname, '..', '..');
  console.log('[global-setup] npm run build を実行し、フロントエンド資産を最新化します...');
  execSync('npm run build', { cwd: repoRoot, stdio: 'inherit' });
}
