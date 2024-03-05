<template>
  <div class="ipetometer primary-light-background bg-cream box-shape-placard-4" ref="ometer">
    <div class="ipetometer__domain" >
      <div class="ipetometer__bar" :style="barStyle"></div>
    </div>
    <span class="ipetometer__bignum">{{ count.toLocaleString() }}</span>
    <span class="ipetometer__words">{{stmt}}</span>
    <span class="ipetometer__target">
      <span v-show="stretchTarget && stretchTarget > target" class="stretch">Original target ({{target.toLocaleString()}}) exceeded! Let’s go for {{stretchTarget.toLocaleString()}}</span>
      <span v-show="!stretchTarget || stretchTarget < target" class="original">Target {{target.toLocaleString()}}</span>
    </span>
    <div class="ipetometer__last" v-show="lastSigner" >Last signed by {{lastSigner.name}}, {{lastSigner.ago}}</div>
  </div>
</template>
<script>
export default {
  props: ['count', 'stmt', 'target', 'stretchTarget', 'lastSigner'],
  data() {
    return {
      animStart: false,
      step: 0,
      animDoneOnce: false,

      containerSize:false,
      debounce: false,
    };
  },
  computed:{
    targetInUse() {
      console.log("OMETER ", {target:this.target});
      if (!this.target) {
        // No target?
        var m = (this.count > 10000)
          ? 10000
          : ((this.count > 1000)
            ? 1000
            : 100);
        return Math.ceil((this.count / 0.75) / m) * m;
      }
      else {
        return (this.stretchTarget && this.stretchTarget>this.target) ? this.stretchTarget : this.target;
      }
    },
    barStyle() {
      var s = this.step;
      s = s*s;
      return {
        width: (s * this.count / this.targetInUse * 100) + '%'
      };
    },
  },
  mounted() {
    var observer = new IntersectionObserver(this.handleIntersectionChange.bind(this), {
      // root: this.$refs.treesContainer,
      // rootMargin: '0px',
      threshold: 1.0
    });
    observer.observe(this.$refs.ometer);
  },
  methods:{
    handleIntersectionChange(entries, observer) {
      entries.forEach(e => {
        if (e.isIntersecting && !this.animDoneOnce) {
          this.startAnimation();
          this.animDoneOnce = true;
        }
      });
    },
    startAnimation() {
      this.animStart = false;
      window.requestAnimationFrame(this.animate.bind(this));
    },
    animate(t) {
      if (!this.animStart) {
        this.animStart = t;
      }
      // Allow 1 s for the animation.
      this.step = Math.min(1, (t - this.animStart) / 1000);
      if (this.step < 1) {
        window.requestAnimationFrame(this.animate.bind(this));
      }
    }
  }
}
</script>
<style lang="scss">

.ipetometer {
  display: flex;
  flex-wrap:wrap;
  align-items: center;
  justify-content: space-between;
  line-height: 1;
  padding: 1rem;
  margin-bottom: 1rem;
  font-weight: bold;

  .ipetometer__domain {
    flex: 0 0 100%;
    background: white;
    overflow: hidden;
  }
  .ipetometer__bar {
    background: #fc0;
    height: 1rem;
  }

  .ipetometer__bignum {
    flex: 0 0 auto;
    padding-right: 1rem;
    line-height: 1.5;
    font-size:3rem;
  }
  .ipetometer__words {
    flex: 1 1 auto;
    font-size: 1rem;
  }
  .ipetometer__target {
    flex: 0 0 auto;
    font-size: 1rem;
  }
  .ipetometer__last {
    font-weight: normal;
    font-size: 0.825rem;
    padding-top: 0.5rem;
  }
}
</style>
