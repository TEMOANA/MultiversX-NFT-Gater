const https = require("https");

const url = "https://unpkg.com/elven.js@0.20.0/build/elven.js";
https.get(url, (res) => {
  let data = "";
  res.on("data", (chunk) => {
    data += chunk;
  });
  res.on("end", () => {
    let index = data.indexOf("onLoginSuccess");
    while (index !== -1) {
      const start = Math.max(0, index - 80);
      const end = Math.min(data.length, index + 100);
      console.log(`[Match] ...${data.slice(start, end).replace(/\n/g, " ")}...`);
      index = data.indexOf("onLoginSuccess", index + 1);
    }
  });
}).on("error", (err) => {
  console.error("Error:", err.message);
});
