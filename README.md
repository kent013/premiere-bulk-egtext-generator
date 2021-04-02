# premiere-bulk-egtext-generator
Generate premiere essential graphic text from a text file

**音声認識結果のSRTファイル** と **一行一字幕で書き起こしした字幕テキストファイル** を対応づけして、書き起こしのテキストにタイムコードをつけるプログラムです。
日本語をカタカナに変換してからDPマッチングで関連付けします。SRTファイルの文字列と字幕テキストファイルがあまりにも乖離していると精度が悪いです。

## Export FinalCut Pro Xml
```
php -f scripts/generator.php -- -o test.xml -f ~/Documents/長野先生Youtube/素材/字幕/手動処理済み/012.txt -s ~/Documents/長野先生Youtube/素材/字幕/音声認識結果/012.srt
```

## Export Srt
As of Premiere Pro 2021, Premiere supports caption future and you can import srt file to the project directly. So that you don't need FinalCut Pro xml anymore.

```
php -f scripts/generator.php -- -o test.srt -f ~/Documents/長野先生Youtube/素材/字幕/手動処理済み/012.txt -s ~/Documents/長野先生Youtube/素材/字幕/音声認識結果/012.srt
```

