import commonjs from "@rollup/plugin-commonjs";
import resolve from "@rollup/plugin-node-resolve";
import typescript from "@rollup/plugin-typescript";
import terser from "@rollup/plugin-terser";

export default {
  input: "ts/main.ts",
  output: {
    file: "public/js/main.min.js",
    format: "es",
    sourcemap: true,
  },
  plugins: [commonjs(), resolve(), typescript(), terser()],
  watch: {
    include: ["ts/**/*"],
  },
};
