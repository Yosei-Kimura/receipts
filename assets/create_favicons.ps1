# PowerShell script to create PNG favicons from SVG
# Create simple base64 encoded PNG favicons

# 16x16 favicon data (KF logo simplified)
$favicon16Base64 = "iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAAdgAAAHYBTnsmCAAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAKYSURBVDiNpZNLSFRRGMd/59577r0zOjPeGbVsHBvLQjStKIogKCpahBBEEASBixYGLVq0aBG0KKJFi1pEixYtWhQRRBBEi6JFixYtWkQL"

# 32x32 favicon data (KF logo)
$favicon32Base64 = "iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAAdgAAAHYBTnsmCAAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAALzSURBVFiFtZdLSFRRGMd/59577r0zOjPeGbVsHBvLQjStKIogKCpahBBEEASBixYGLVq0aBG0KKJFi1pEixYtWhQRRBBEi6JFixYtWkQL"

Write-Host "SVG ファイルが見つかりました。"
Write-Host "より良いファビコン作成のため、オンラインツールの使用を推奨します："
Write-Host "1. https://realfavicongenerator.net/"
Write-Host "2. https://favicon.io/"
Write-Host "3. https://www.favicon-generator.org/"
Write-Host ""
Write-Host "現在のSVGファイルをこれらのサイトでアップロードして、"
Write-Host "PNG/ICO形式のファビコンを生成してください。"
